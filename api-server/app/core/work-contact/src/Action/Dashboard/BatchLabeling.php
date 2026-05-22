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
use MoChat\App\WorkContact\Contract\WorkContactEmployeeContract;
use MoChat\App\WorkContact\Contract\WorkContactTagContract;
use MoChat\App\WorkContact\Contract\WorkContactTagPivotContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 批量打标签.
 *
 * Class BatchLabeling
 * @Controller
 */
class BatchLabeling extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var WorkContactTagPivotContract
     */
    private $contactTagPivotService;

    /**
     * @Inject
     * @var WorkContactEmployeeContract
     */
    private $contactEmployeeService;

    /**
     * @Inject
     * @var WorkContactTagContract
     */
    private $contactTagService;

    /**
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class),
     *     @Middleware(PermissionMiddleware::class)
     * })
     * @RequestMapping(path="/dashboard/workContact/batchLabeling", methods="POST")
     */
    public function handle()
    {
        //接收参数
        $params['contactId'] = $this->request->input('contactId');
        $params['tagId'] = $this->request->input('tagId');

        //校验参数
        $this->validated($params);

        //客户id
        $contactIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $params['contactId'])))));
        //标签id
        $tagIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $params['tagId'])))));
        $this->assertBatchLabelScope($contactIds, $tagIds);
        //查询客户已有标签id
        $columns = [
            'contact_id',
            'employee_id',
            'contact_tag_id',
        ];

        $contactInfo = array_filter($this->contactTagPivotService->getWorkContactTagPivotsByContactIdsTagIds($contactIds, $tagIds, $columns), function ($item) {
            return (int) ($item['employeeId'] ?? 0) === (int) user()['workEmployeeId'];
        });

        //例如：客户A选择选了标签，“优质”、“跟进中”，客户B选择了标签“意向强烈”、“跟进中”，
        //那么两个客户在批量打标签的时候“跟进中”这个标签置灰不可以选择，
        //但是“优质”和“意向强烈”是可以进行选择，选择后对应的标签添加到原来并未设置的客户下

        $data = [];
        foreach ($contactIds as $val) {
            foreach ($tagIds as $v) {
                $data[] = [
                    'contact_id' => $val,
                    'employee_id' => user()['workEmployeeId'],
                    'contact_tag_id' => $v,
                ];
            }
        }

        foreach ($data as $key => $raw) {
            foreach ($contactInfo as $item) {
                //如果已经添加过该标签
                if ($raw['contact_id'] == $item['contactId'] && $raw['contact_tag_id'] == $item['contactTagId']) {
                    unset($data[$key]);
                }
            }
        }

        //批量添加标签
        $res = $this->contactTagPivotService->createWorkContactTagPivots($data);
        if ($res != true) {
            throw new CommonException(ErrorCode::SERVER_ERROR, '批量打标签失败');
        }
    }

    /**
     * @return string[] 规则
     */
    public function rules(): array
    {
        return [
            'contactId' => 'required',
            'tagId' => 'required',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息.
     */
    public function messages(): array
    {
        return [
            'contactId.required' => '客户id必传',
            'tagId.required' => '标签id必传',
        ];
    }

    private function assertBatchLabelScope(array $contactIds, array $tagIds): void
    {
        if (empty($contactIds) || empty($tagIds)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '客户或标签不能为空');
        }

        $user = user();
        $corpId = (int) $user['corpIds'][0];
        $employeeId = (int) $user['workEmployeeId'];
        $relations = $this->contactEmployeeService->getWorkContactEmployeeByOtherIds([$employeeId], $contactIds, ['id', 'contact_id', 'employee_id', 'corp_id']);
        $relationContactIds = array_map('intval', array_column($relations, 'contactId'));
        if (count(array_unique($relationContactIds)) !== count($contactIds)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '只能给当前成员持有的客户批量打标签');
        }
        foreach ($relations as $relation) {
            if ((int) $relation['corpId'] !== $corpId) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, '客户不属于当前企业');
            }
        }

        if (! empty($user['dataPermission']) && ! in_array($employeeId, array_map('intval', $user['deptEmployeeIds'] ?? []), true)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '当前成员不在数据范围内');
        }

        $tags = $this->contactTagService->getWorkContactTagsById($tagIds, ['id', 'corp_id']);
        if (count($tags) !== count($tagIds)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '标签不存在');
        }
        foreach ($tags as $tag) {
            if ((int) $tag['corpId'] !== $corpId) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, '标签不属于当前企业');
            }
        }
    }
}
