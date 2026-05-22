<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\User\Action\Dashboard;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use MoChat\App\Rbac\Contract\RbacRoleContract;
use MoChat\App\Rbac\Contract\RbacUserRoleContract;
use MoChat\App\Rbac\Middleware\PermissionMiddleware;
use MoChat\App\User\Contract\UserContract;
use MoChat\App\WorkDepartment\Contract\WorkDepartmentContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeDepartmentContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 子账户管理- 详情.
 *
 * Class Show.
 * @Controller
 */
class Show extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var UserContract
     */
    protected $userService;

    /**
     * @Inject
     * @var RbacUserRoleContract
     */
    protected $rbacUserRoleService;

    /**
     * @Inject
     * @var RbacRoleContract
     */
    protected $rbacRoleService;

    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $workEmployeeService;

    /**
     * @Inject
     * @var WorkEmployeeDepartmentContract
     */
    protected $workEmployeeDepartmentService;

    /**
     * @Inject
     * @var WorkDepartmentContract
     */
    protected $workDepartmentService;

    /**
     * @RequestMapping(path="/dashboard/user/show", methods="get")
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @return array 返回数组
     */
    public function handle(): array
    {
        ## 参数验证
        $this->validated($this->request->all());
        ## 获取当前登录用户
        $user = user();
        ## 接收参数
        $userId = $this->request->input('userId');
        ## 查询数据
        $data = $this->userService->getUserById((int) $userId);
        if (empty($data) || (int) $data['tenantId'] !== (int) $user['tenantId']) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '当前账户不存在');
        }
        $employee = $this->assertVisibleTarget((int) $data['id'], $user);
        ## 角色信息
        $roleInfo = $this->findRoleInfo((int) $data['id']);

        $data['userId'] = $data['id'];
        $data['userName'] = $data['name'];
        $data['roleId'] = $roleInfo['roleId'];
        $data['roleName'] = $roleInfo['roleName'];
        $data['employee'] = $this->formatEmployeeInfo($employee);
        $data['employeeId'] = $data['employee']['employeeId'] ?? 0;
        $data['department'] = $this->getDepartmentListByEmployeeId((int) ($employee['id'] ?? 0));
        unset($data['id'], $data['name'], $data['position'], $data['loginTime'], $data['password'], $data['createdAt'], $data['updatedAt'], $data['deletedAt']);

        return $data;
    }

    /**
     * 验证规则.
     *
     * @return array 响应数据
     */
    protected function rules(): array
    {
        return [
            'userId' => 'required | integer | min:0| bail',
        ];
    }

    /**
     * 验证错误提示.
     * @return array 响应数据
     */
    protected function messages(): array
    {
        return [
            'userId.required' => '用户ID 必填',
            'userId.integer' => '用户ID 必需为整数',
            'userId.min' => '用户ID 不可小于1',
        ];
    }

    /**
     * @param int $userId 用户ID
     * @return array 响应数组
     */
    private function findRoleInfo(int $userId): array
    {
        $data = [
            'roleId' => 0,
            'roleName' => '',
        ];
        $userRole = $this->rbacUserRoleService->getRbacUserRoleByUserId($userId, ['id', 'role_id']);
        if (empty($userRole)) {
            return $data;
        }
        $role = $this->rbacRoleService->getRbacRoleById((int) $userRole['roleId'], ['id', 'name']);
        return empty($role) ? $data : [
            'roleId' => $role['id'],
            'roleName' => $role['name'],
        ];
    }

    private function getDepartmentListByEmployeeId(int $employeeId): array
    {
        if (empty($employeeId)) {
            return [];
        }
        $employeeDepartments = $this->workEmployeeDepartmentService->getWorkEmployeeDepartmentsByEmployeeId($employeeId, ['department_id']);
        if (empty($employeeDepartments)) {
            return [];
        }
        $departmentIdArr = array_column($employeeDepartments, 'departmentId');
        $departmentList = $this->workDepartmentService->getWorkDepartmentsById($departmentIdArr, ['id', 'name']);
        return empty($departmentList) ? [] : array_map(function ($department) {
            return [
                'departmentId' => $department['id'],
                'departmentName' => $department['name'],
            ];
        }, $departmentList);
    }

    private function assertVisibleTarget(int $userId, array $user): array
    {
        $employee = $this->workEmployeeService->getWorkEmployeeByCorpIdLogUserId((int) $user['corpIds'][0], $userId, ['id', 'name', 'wx_user_id']);
        if (! empty($user['isSuperAdmin'])) {
            return $employee;
        }
        if (empty($employee)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '当前账户未绑定当前企业成员');
        }
        if (! empty($user['dataPermission']) && ! in_array((int) $employee['id'], array_map('intval', $user['deptEmployeeIds'] ?? []), true)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '当前账户不在当前数据范围内');
        }
        return $employee;
    }

    private function formatEmployeeInfo(array $employee): array
    {
        if (empty($employee)) {
            return [];
        }
        return [
            'employeeId' => $employee['id'],
            'employeeName' => $employee['name'],
            'wxUserId' => $employee['wxUserId'],
        ];
    }
}
