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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use MoChat\App\Rbac\Middleware\PermissionMiddleware;
use MoChat\App\User\Constants\Status;
use MoChat\App\User\Contract\UserContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 子账户管理- 禁用|启动.
 *
 * Class StatusUpdate.
 * @Controller
 */
class StatusUpdate extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var UserContract
     */
    protected $userService;

    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $workEmployeeService;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    private $logger;

    /**
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @RequestMapping(path="/dashboard/user/statusUpdate", methods="put")
     * @return array 返回数组
     */
    public function index()
    {
        ## 参数验证
        $this->validated($this->request->all());
        $user = user();
        ## 接收参数
        $userId = $this->request->input('userId');
        $status = $this->request->input('status');

        ## 验证userId的有效性
        $userIds = array_values(array_unique(array_map('intval', explode(',', $userId))));
        $users = $this->userService->getUsersById($userIds, ['id', 'name', 'status', 'tenant_id', 'is_super_admin']);
        if (count($userIds) != count($users)) {
            $diffUserIds = array_diff($userIds, array_column($users, 'id'));
            throw new CommonException(ErrorCode::INVALID_PARAMS, sprintf('部分账户信息不存在，账户ID：%s', implode('、', $diffUserIds)));
        }
        $this->assertManageableUsers($users, $user);
        ## 比较操作状态与更新账户的当前状态
        $statusArr = array_column($users, 'status');
        if (in_array($status, $statusArr)) {
            $errUserNames = [];
            foreach ($users as $user) {
                $user['status'] != $status || $errUserNames[] = $user['name'];
            }
            throw new CommonException(ErrorCode::INVALID_PARAMS, sprintf('%s当前状态：%s，不可重复操作', rtrim(implode('、', $errUserNames)), Status::getMessage($status)));
        }

        try {
            ## 数据入库
            $this->userService->updateUserStatusById($userIds, (int) $status);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('%s [%s] %s', '账户状态更新失败', date('Y-m-d H:i:s'), $e->getMessage()));
            $this->logger->error($e->getTraceAsString());
            throw new CommonException(ErrorCode::SERVER_ERROR, '账户状态更新失败');
        }

        return [];
    }

    private function assertManageableUsers(array $targets, array $user): void
    {
        foreach ($targets as $target) {
            if ((int) $target['tenantId'] !== (int) $user['tenantId']) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, sprintf('账户%s不属于当前租户，不可操作', $target['name']));
            }
            if (! empty($target['isSuperAdmin'])) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, sprintf('账户%s为超级管理员，不可在此处操作', $target['name']));
            }
        }
        if (! empty($user['isSuperAdmin'])) {
            return;
        }

        $targetIds = array_column($targets, 'id');
        $employees = $this->workEmployeeService->getWorkEmployeesByLogUserIds($targetIds, ['id', 'corp_id', 'log_user_id']);
        $employeesByUserId = array_column($employees, null, 'logUserId');
        $allowedEmployeeIds = array_map('intval', $user['deptEmployeeIds'] ?? []);
        foreach ($targets as $target) {
            $employee = $employeesByUserId[$target['id']] ?? null;
            if (empty($employee) || (int) $employee['corpId'] !== (int) $user['corpIds'][0]) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, sprintf('账户%s未绑定当前企业成员，不可操作', $target['name']));
            }
            if (! empty($user['dataPermission']) && ! in_array((int) $employee['id'], $allowedEmployeeIds, true)) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, sprintf('账户%s不在当前数据范围内，不可操作', $target['name']));
            }
        }
    }

    /**
     * 验证规则.
     * @return array 响应数据
     */
    protected function rules(): array
    {
        return [
            'userId' => 'required | string | min:1 | bail',
            'status' => 'required | integer | in:1,2, | bail',
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
            'userId.string' => '用户ID 必需为字符串',
            'userId.min' => '用户ID 字符串长度不可小于1',
            'status.required' => '状态 必填',
            'status.integer' => '状态 必需为整数',
            'status.in' => '状态 值必须在列表内：[1,2]',
        ];
    }
}
