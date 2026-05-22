<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\Plugin\Statistic\Logic;

use Hyperf\Di\Annotation\Inject;
use MoChat\App\User\Contract\UserContract;
use MoChat\App\WorkContact\Contract\WorkContactEmployeeContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;

/**
 * Class IndexLogic.
 */
class IndexLogic
{
    /**
     * @Inject
     * @var UserContract
     */
    protected $userService;

    /**
     * @Inject
     * @var WorkContactEmployeeContract
     */
    protected $workContactEmployeeService;

    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $workEmployeeService;

    public function todayData(): array
    {
        $user = user();
        $employeeIds = $this->visibleEmployeeIds($user);

        $todayDate = date('Y-m-d', time());
        $tomorrowDate = date('Y-m-d', time() + 86400);

        if ($employeeIds === null) {
            //企业的客户总数
            $res['total'] = $this->workContactEmployeeService->countWorkContactEmployeesByCorpId($user['corpIds'][0], [1]);
            //企业新增的客户总数
            $res['add'] = $this->workContactEmployeeService->countWorkContactEmployeesByCorpIdTime($user['corpIds'][0], $todayDate, $tomorrowDate);
            //企业流失的客户总数
            $res['loss'] = $this->workContactEmployeeService->countLossWorkContactEmployeesByCorpIdTime($user['corpIds'][0], $todayDate, $tomorrowDate);
        } else {
            $res['total'] = $this->workContactEmployeeService->countWorkContactEmployeesByCorpIdEmployeeIds($user['corpIds'][0], $employeeIds, [1]);
            $res['add'] = $this->workContactEmployeeService->countWorkContactEmployeesByCorpIdEmployeeIdsTime($user['corpIds'][0], $employeeIds, $todayDate, $tomorrowDate);
            $res['loss'] = $this->workContactEmployeeService->countLossWorkContactEmployeesByCorpIdEmployeeIdsTime($user['corpIds'][0], $employeeIds, $todayDate, $tomorrowDate);
        }

        //企业净增的客户总数
        $res['net'] = $res['add'] - $res['loss'];

        return $res;
    }

    /**
     * @param array $params
     */
    public function anyTimeOrEmployeesData($params): array
    {
        $user = user();

        $start = strtotime($params['startTime']);
        $end = strtotime($params['endTime']);

        $res = [];
        if ($params['employeeId']) {
            $employeeIds = $this->filterVisibleEmployeeIds((array) $params['employeeId'], $user);
            //统计员工
            for ($i = $start; $i <= $end; $i += 86400) {
                $todayText = date('Y/m/d', $i);
                $tomorrowText = date('Y/m/d', $i + 86400);

                $res[$todayText]['total'] = $this->workContactEmployeeService->countWorkContactEmployeesByEmployeeIdsTime($user['corpIds'][0], $employeeIds, [1], $tomorrowText);
                $res[$todayText]['add'] = $this->workContactEmployeeService->countWorkContactEmployeesByCorpIdEmployeeIdsTime($user['corpIds'][0], $employeeIds, $todayText, $tomorrowText);
                $res[$todayText]['loss'] = $this->workContactEmployeeService->countLossWorkContactEmployeesByCorpIdEmployeeIdsTime($user['corpIds'][0], $employeeIds, $todayText, $tomorrowText);
                $res[$todayText]['net'] = $res[$todayText]['add'] - $res[$todayText]['loss'];
            }
        } else {
            $employeeIds = $this->visibleEmployeeIds($user);
            //统计企业
            for ($i = $start; $i <= $end; $i += 86400) {
                $todayText = date('Y/m/d', $i);
                $tomorrowText = date('Y/m/d', $i + 86400);

                if ($employeeIds === null) {
                    $res[$todayText]['total'] = $this->workContactEmployeeService->countWorkContactEmployeesByTime($user['corpIds'][0], [1], $tomorrowText);
                    $res[$todayText]['add'] = $this->workContactEmployeeService->countWorkContactEmployeesByCorpIdTime($user['corpIds'][0], $todayText, $tomorrowText);
                    $res[$todayText]['loss'] = $this->workContactEmployeeService->countLossWorkContactEmployeesByCorpIdTime($user['corpIds'][0], $todayText, $tomorrowText);
                } else {
                    $res[$todayText]['total'] = $this->workContactEmployeeService->countWorkContactEmployeesByEmployeeIdsTime($user['corpIds'][0], $employeeIds, [1], $tomorrowText);
                    $res[$todayText]['add'] = $this->workContactEmployeeService->countWorkContactEmployeesByCorpIdEmployeeIdsTime($user['corpIds'][0], $employeeIds, $todayText, $tomorrowText);
                    $res[$todayText]['loss'] = $this->workContactEmployeeService->countLossWorkContactEmployeesByCorpIdEmployeeIdsTime($user['corpIds'][0], $employeeIds, $todayText, $tomorrowText);
                }
                $res[$todayText]['net'] = $res[$todayText]['add'] - $res[$todayText]['loss'];
            }
        }

        return $res;
    }

    private function visibleEmployeeIds(array $user): ?array
    {
        return empty($user['dataPermission']) ? null : array_map('intval', $user['deptEmployeeIds'] ?? []);
    }

    private function filterVisibleEmployeeIds(array $employeeIds, array $user): array
    {
        $employeeIds = array_map('intval', $employeeIds);
        $corpEmployees = $this->workEmployeeService->getWorkEmployeesById($employeeIds, ['id', 'corp_id']);
        $corpEmployeeIds = [];
        foreach ($corpEmployees as $employee) {
            if ((int) $employee['corpId'] === (int) $user['corpIds'][0]) {
                $corpEmployeeIds[] = (int) $employee['id'];
            }
        }
        if (! empty($user['dataPermission'])) {
            $corpEmployeeIds = array_values(array_intersect($corpEmployeeIds, array_map('intval', $user['deptEmployeeIds'] ?? [])));
        }

        return array_values(array_unique($corpEmployeeIds));
    }
}
