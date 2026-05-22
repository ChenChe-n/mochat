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
use MoChat\App\User\Contract\UserContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Framework\Request\ValidateSceneTrait;
use Qbhy\HyperfAuth\AuthManager;
use Qbhy\SimpleJwt\JWTManager;

/**
 * 子账户管理- 管理员重置密码
 *
 * Class PasswordReset.
 * @Controller
 */
class PasswordReset extends AbstractAction
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
     * @var AuthManager
     */
    protected $authManager;

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
     * @RequestMapping(path="/dashboard/user/passwordReset", methods="put")
     * @return array 返回数组
     */
    public function index()
    {
        ## 参数验证
        $this->validated($this->request->all());
        ## 当前登录用户
        $user = user();
        ## 接收参数
        $userId = $this->request->input('id');
        $newPassword = $this->request->input('newPassword');

        $this->assertManageableUser((int) $userId, $user);

        ## 验证当前用户的有效性
        $currentUser = $this->userService->getUserById((int) $user['id'], ['id']);
        if (empty($currentUser)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '当前账户不存在，不可操作');
        }
        ## 生成新密码
        $guard = $this->authManager->guard('jwt');
        /** @var JWTManager $jwt */
        $jwt = $guard->getJwtManager();
        $newPassword = $jwt->getEncrypter()->signature($newPassword);

        try {
            ## 数据入库
            $this->userService->updateUserPasswordById((int) $userId, $newPassword);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('%s [%s] %s', '账户更新密码失败', date('Y-m-d H:i:s'), $e->getMessage()));
            $this->logger->error($e->getTraceAsString());
            throw new CommonException(ErrorCode::SERVER_ERROR, '账户更新密码失败');
        }
        return [];
    }

    private function assertManageableUser(int $targetUserId, array $user): void
    {
        $targetUser = $this->userService->getUserById($targetUserId, ['id', 'tenant_id', 'is_super_admin']);
        if (empty($targetUser) || (int) $targetUser['tenantId'] !== (int) $user['tenantId']) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '目标账户不存在，不可操作');
        }
        if (! empty($targetUser['isSuperAdmin'])) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '超级管理员账户不可在此处重置密码');
        }
        if (! empty($user['isSuperAdmin'])) {
            return;
        }

        $employee = $this->workEmployeeService->getWorkEmployeeByCorpIdLogUserId((int) $user['corpIds'][0], $targetUserId, ['id']);
        if (empty($employee)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '目标账户未绑定当前企业成员，不可操作');
        }
        if (! empty($user['dataPermission']) && ! in_array((int) $employee['id'], array_map('intval', $user['deptEmployeeIds'] ?? []), true)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '目标账户不在当前数据范围内，不可操作');
        }
    }

    /**
     * 验证规则.
     *
     * @return array 响应数据
     */
    protected function rules(): array
    {
        return [
            'id' => 'required|min:1',
            'newPassword' => 'required|string|min:1|bail',
        ];
    }

    /**
     * 验证错误提示.
     * @return array 响应数据
     */
    protected function messages(): array
    {
        return [
            'id.required' => '用户id 必填',
            'newPassword.required' => '新密码 必填',
            'newPassword.min' => '新密码 不可为空',
        ];
    }
}
