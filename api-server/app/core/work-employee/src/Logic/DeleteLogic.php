<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\WorkEmployee\Logic;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;

class DeleteLogic
{
    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $employeeService;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    private $logger;

    public function handle(array $params, array $user): array
    {
        $employeeId = (int) $params['employeeId'];
        $corpId = (int) $user['corpIds'][0];
        $employee = $this->employeeService->getWorkEmployeeById($employeeId, ['id', 'corp_id', 'log_user_id']);
        if (empty($employee) || (int) $employee['corpId'] !== $corpId) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '当前成员不存在，不可删除');
        }

        Db::beginTransaction();
        try {
            Db::table('work_employee_department')->where('employee_id', $employeeId)->delete();
            Db::table('work_employee_statistic')->where('employee_id', $employeeId)->delete();
            Db::table('work_employee_tag_pivot')->where('employee_id', $employeeId)->delete();

            $this->employeeService->updateWorkEmployeeById($employeeId, ['log_user_id' => 0]);
            $this->employeeService->deleteWorkEmployee($employeeId);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->logger->error(sprintf('%s [%s] %s', '成员删除失败', date('Y-m-d H:i:s'), $e->getMessage()));
            $this->logger->error($e->getTraceAsString());
            throw new CommonException(ErrorCode::SERVER_ERROR, '成员删除失败');
        }

        return [];
    }
}
