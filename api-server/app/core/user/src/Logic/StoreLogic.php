<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\User\Logic;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use MoChat\App\Rbac\Contract\RbacUserRoleContract;
use MoChat\App\User\Contract\UserContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use Qbhy\HyperfAuth\AuthManager;
use Qbhy\SimpleJwt\JWTManager;

/**
 * 子账户管理- 创建提交.
 *
 * Class StoreLogic
 */
class StoreLogic
{
    /**
     * @Inject
     * @var UserContract
     */
    protected $userService;

    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $employeeService;

    /**
     * @Inject
     * @var RbacUserRoleContract
     */
    protected $rbacUserRoleService;

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
     * @param array $params 请求参数
     * @param array $user 当前登录用户信息
     * @return array 响应数组
     */
    public function handle(array $params, array $user): array
    {
        ## 验证手机号
        $phoneUser = $this->userService->getUsersByPhone([$params['phone']], ['id']);
        if (! empty($phoneUser)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '手机号已存在，不可重复创建');
        }
        ## 处理数据
        $params = $this->handleParams($params);
        ## 数据入表
        $this->insertData($params, $user);

        return [];
    }

    /**
     * @param array $params 请求参数
     * @return array 响应数组
     */
    private function handleParams(array $params): array
    {
        ## 生成初始密码
        $guard = $this->authManager->guard('jwt');
        /** @var JWTManager $jwt */
        $jwt = $guard->getJwtManager();
        $params['password'] = $jwt->getEncrypter()->signature($params['password']);
        $params['created_at'] = date('Y-m-d H:i:s');

        return $params;
    }

    /**
     * @param array $params 请求参数
     * @param array $user 当前登录用户信息
     */
    private function insertData(array $params, array $user)
    {
        ## 角色信息
        $roleId = $params['roleId'];
        $employeeId = (int) $params['employeeId'];
        unset($params['roleId'], $params['employeeId']);
        $corpId = (int) $user['corpIds'][0];
        ## 根据企业微信成员ID绑定子账户，不再依赖企微手机号返回值
        $employeeData = $this->getBindableEmployee($corpId, $employeeId);
        ## 数据操作
        Db::beginTransaction();
        try {
            ## 插入用户
            $userId = $this->userService->createUser($params);
            ## 更新用户通讯录表
            $this->employeeService->updateWorkEmployeeById((int) $employeeData['id'], ['log_user_id' => $userId]);
            ## 插入用户角色
            empty($roleId) || $this->rbacUserRoleService->createRbacUserRole([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->logger->error(sprintf('%s [%s] %s', '账户创建失败', date('Y-m-d H:i:s'), $e->getMessage()));
            $this->logger->error($e->getTraceAsString());
            throw new CommonException(ErrorCode::SERVER_ERROR, '账户创建失败');
        }
    }

    private function getBindableEmployee(int $corpId, int $employeeId): array
    {
        $employee = $this->employeeService->getWorkEmployeeById($employeeId, ['id', 'corp_id', 'log_user_id', 'wx_user_id']);
        if (empty($employee) || (int) $employee['corpId'] !== $corpId || empty($employee['wxUserId'])) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '请选择当前企业下有效的企业微信成员');
        }
        if (! empty($employee['logUserId'])) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '该企业微信成员已绑定其他子账户');
        }

        return $employee;
    }
}
