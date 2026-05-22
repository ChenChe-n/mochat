<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\WorkContact\Action\Dashboard;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use MoChat\App\Rbac\Middleware\PermissionMiddleware;
use MoChat\App\WorkContact\Contract\ContactEmployeeTrackContract;
use MoChat\App\WorkContact\Contract\WorkContactEmployeeContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 互动轨迹.
 *
 * Class Track
 * @Controller
 */
class Track extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * 互动轨迹表.
     * @Inject
     * @var ContactEmployeeTrackContract
     */
    private $track;

    /**
     * 员工 - 客户关联.
     * @Inject
     * @var WorkContactEmployeeContract
     */
    private $contactEmployee;

    /**
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @RequestMapping(path="/dashboard/workContact/track", methods="GET")
     */
    public function handle()
    {
        //接收参数
        $params['contactId'] = $this->request->input('contactId');
        //校验参数
        $this->validated($params);
        $this->assertVisibleContact((int) $params['contactId']);

        $columns = [
            'id',
            'content',
            'created_at',
        ];
        $tracks = $this->track->getContactEmployeeTracksByContactId((int) $params['contactId'], array_merge($columns, ['corp_id', 'employee_id']));
        $visibleEmployeeIds = empty(user()['dataPermission']) ? null : array_map('intval', user()['deptEmployeeIds'] ?? []);
        $tracks = array_filter($tracks, function ($track) use ($visibleEmployeeIds) {
            if ((int) $track['corpId'] !== (int) user()['corpIds'][0]) {
                return false;
            }
            return $visibleEmployeeIds === null || in_array((int) $track['employeeId'], $visibleEmployeeIds, true);
        });

        return array_map(function ($track) {
            unset($track['corpId'], $track['employeeId']);
            return $track;
        }, array_values($tracks));
    }

    private function assertVisibleContact(int $contactId): void
    {
        $user = user();
        $relations = $this->contactEmployee->getWorkContactEmployeesByCorpIdContactId((int) $user['corpIds'][0], $contactId, ['id', 'employee_id']);
        if (empty($relations)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '客户不属于当前企业');
        }
        if (empty($user['dataPermission'])) {
            return;
        }
        $visibleEmployeeIds = array_map('intval', $user['deptEmployeeIds'] ?? []);
        $employeeIds = array_map('intval', array_column($relations, 'employeeId'));
        if (empty(array_intersect($employeeIds, $visibleEmployeeIds))) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '客户不在当前数据范围内');
        }
    }

    /**
     * @return string[] 规则
     */
    public function rules(): array
    {
        return [
            'contactId' => 'required|integer|min:1|bail',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息.
     */
    public function messages(): array
    {
        return [
            'contactId.required' => '客户id必传',
        ];
    }
}
