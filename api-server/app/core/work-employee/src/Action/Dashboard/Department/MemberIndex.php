<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\WorkEmployee\Action\Dashboard\Department;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use MoChat\App\Rbac\Middleware\PermissionMiddleware;
use MoChat\App\WorkDepartment\Contract\WorkDepartmentContract;
use MoChat\App\WorkEmployee\Constants\Status;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeDepartmentContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 部门下的成员列表.
 *
 * Class MemberIndex
 * @Controller
 */
class MemberIndex extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * 部门 - 成员 关联.
     * @Inject
     * @var WorkEmployeeDepartmentContract
     */
    private $employeeDepartmentService;

    /**
     * 部门.
     * @Inject
     * @var WorkDepartmentContract
     */
    private $departmentService;

    /**
     * 成员.
     * @Inject
     * @var WorkEmployeeContract
     */
    private $employeeService;

    /**
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @RequestMapping(path="/dashboard/workEmployeeDepartment/memberIndex", methods="GET")
     * @return array 响应数据
     */
    public function handle()
    {
        //参数
        $params['departmentIds'] = $this->request->input('departmentIds');
        //验证
        $this->validated($params);

        //获取信息
        return $this->getEmployeeInfo($params['departmentIds'], user());
    }

    /**
     * @return string[] 规则
     */
    public function rules(): array
    {
        return [
            'departmentIds' => 'required|string',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息.
     */
    public function messages(): array
    {
        return [
            'departmentIds.required' => '部门id必传',
        ];
    }

    /**
     * 获取成员信息.
     * @param $departmentIds
     * @return array
     */
    private function getEmployeeInfo($departmentIds, array $user)
    {
        $corpId = (int) $user['corpIds'][0];
        $departmentIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $departmentIds)))));
        if (empty($departmentIds)) {
            return [];
        }

        $departmentInfo = $this->departmentService->getWorkDepartmentsBySearch([
            'corp_id' => $corpId,
            'id' => $departmentIds,
        ], ['id', 'name']);
        if (empty($departmentInfo)) {
            return [];
        }
        $departmentIds = array_column($departmentInfo, 'id');

        //查询已激活成员信息
        $employeeInfo = $this->employeeService->getWorkEmployeesByCorpIdStatus(
            $corpId,
            (int) Status::ACTIVE,
            ['id', 'name']
        );
        if (empty($employeeInfo)) {
            return [];
        }
        if (! empty($user['dataPermission'])) {
            $visibleEmployeeIds = array_map('intval', $user['deptEmployeeIds'] ?? []);
            if (empty($visibleEmployeeIds)) {
                return [];
            }
            $employeeInfo = array_values(array_filter($employeeInfo, function ($employee) use ($visibleEmployeeIds) {
                return in_array((int) $employee['id'], $visibleEmployeeIds, true);
            }));
            if (empty($employeeInfo)) {
                return [];
            }
        }
        $employeeInfo = array_column($employeeInfo, null, 'id');
        $activeEmployeeIds = array_column($employeeInfo, 'id');

        //获取部门、成员关联信息
        $employeeDepartment = $this->employeeDepartmentService
            ->getWorkEmployeeDepartmentsByOtherId($departmentIds, $activeEmployeeIds, ['employee_id', 'department_id']);

        if (empty($employeeDepartment)) {
            return [];
        }

        $departmentInfo = array_column($departmentInfo, null, 'id');

        foreach ($employeeDepartment as &$val) {
            $val['departmentName'] = '';
            $val['employeeName'] = '';
            if (isset($departmentInfo[$val['departmentId']])) {
                $val['departmentName'] = $departmentInfo[$val['departmentId']]['name'];
            }
            if (isset($employeeInfo[$val['employeeId']])) {
                $val['employeeName'] = $employeeInfo[$val['employeeId']]['name'];
            }
        }
        unset($val);

        return $employeeDepartment;
    }
}
