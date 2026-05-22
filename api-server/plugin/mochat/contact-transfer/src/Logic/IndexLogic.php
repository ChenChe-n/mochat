<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\Plugin\ContactTransfer\Logic;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use MoChat\App\Corp\Contract\CorpContract;
use MoChat\App\Corp\Logic\AppTrait;
use MoChat\App\WorkContact\Contract\WorkContactContract;
use MoChat\App\WorkContact\Contract\WorkContactEmployeeContract;
use MoChat\App\WorkContact\Contract\WorkContactTagContract;
use MoChat\App\WorkContact\Contract\WorkContactTagPivotContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\Framework\Constants\ErrorCode;
use MoChat\Framework\Exception\CommonException;
use MoChat\Plugin\ContactTransfer\Contract\WorkTransferLogContract;
use MoChat\Plugin\ContactTransfer\Contract\WorkUnassignedContract;

/**
 * Class IndexLogic.
 */
class IndexLogic
{
    use AppTrait;

    /**
     * @Inject
     * @var WorkTransferLogContract
     */
    protected $workTransferLogService;

    /**
     * @Inject
     * @var CorpContract
     */
    protected $corpService;

    /**
     * @Inject
     * @var WorkUnassignedContract
     */
    protected $workUnassignedService;

    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $workEmployeeService;

    /**
     * @Inject
     * @var WorkContactEmployeeContract
     */
    protected $workContactEmployeeService;

    /**
     * @Inject
     * @var WorkContactContract
     */
    protected $workContactService;

    /**
     * @Inject
     * @var WorkContactTagContract
     */
    protected $workContactTagService;

    /**
     * @Inject
     * @var WorkContactTagPivotContract
     */
    protected $workContactTagPivotService;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    private $logger;

    /**
     * 获分配在职或离职客户.
     * @param $params
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return array
     */
    public function handle($params)
    {
        $this->assertTransferScope($params);

        $wx = $this->wxApp($params['corpId'], 'contact')->external_contact;
        $result = [];
        //离职分配
        if ($params['type'] == 1) {
            foreach ($params['list'] as $param) {
                $temp = $wx->transferCustomer([$param->contactWxId], $param->employeeWxId, $params['takeoverUserId'], '');
                $result[] = $temp;
                if ($temp['errcode'] === 0) {
                    $this->workTransferLogService->createWorkTransferLog([
                        'corp_id' => $params['corpId'],
                        'status' => 1,
                        'type' => 1,
                        'name' => $this->workContactService->getWorkContactByCorpIdWxExternalUserId($params['corpId'], $param->contactWxId),
                        'contact_id' => $param->contactWxId,
                        'handover_employee_id' => $param->employeeWxId,
                        'takeover_employee_id' => $params['takeoverUserId'],
                    ]);
                }
            }
        }
        //在职分配
        if ($params['type'] == 2) {
            foreach ($params['list'] as $param) {
                $temp = $wx->transferCustomer([$param->contactWxId], $param->employeeWxId, $params['takeoverUserId'], '');
                $result[] = $temp;
                if ($temp['errcode'] === 0) {
                    $this->workTransferLogService->createWorkTransferLog([
                        'corp_id' => $params['corpId'],
                        'status' => 2,
                        'type' => 1,
                        'name' => $this->workContactService->getWorkContactByCorpIdWxExternalUserId($params['corpId'], $param->contactWxId)['name'],
                        'contact_id' => $param->contactWxId,
                        'handover_employee_id' => $param->employeeWxId,
                        'takeover_employee_id' => $params['takeoverUserId'],
                    ]);
                }
            }
        }

        return $result;
    }

    private function assertTransferScope(array $params): void
    {
        $user = user();
        $corpId = (int) $params['corpId'];
        $visibleEmployeeIds = empty($user['dataPermission']) ? null : array_map('intval', $user['deptEmployeeIds'] ?? []);

        $takeoverEmployee = $this->workEmployeeService->getWorkEmployeeByCorpIdAndWxUserId($corpId, (string) $params['takeoverUserId'], ['id', 'corp_id']);
        if (empty($takeoverEmployee)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '接替成员不属于当前企业');
        }
        if ($visibleEmployeeIds !== null && empty($visibleEmployeeIds)) {
            throw new CommonException(ErrorCode::INVALID_PARAMS, '当前账号没有可操作的成员范围');
        }

        foreach ($params['list'] as $param) {
            $handoverEmployee = $this->workEmployeeService->getWorkEmployeeByCorpIdAndWxUserId($corpId, (string) $param->employeeWxId, ['id', 'corp_id']);
            $contact = $this->workContactService->getWorkContactByCorpIdWxExternalUserId($corpId, (string) $param->contactWxId, ['id']);
            if (empty($handoverEmployee) || empty($contact)) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, '待分配客户或原成员不属于当前企业');
            }
            $contactEmployee = $this->workContactEmployeeService->findWorkContactEmployeeByOtherIds((int) $handoverEmployee['id'], (int) $contact['id'], ['id']);
            if (empty($contactEmployee)) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, '待分配客户不属于原成员');
            }
            if ($visibleEmployeeIds !== null && ! in_array((int) $handoverEmployee['id'], $visibleEmployeeIds, true)) {
                throw new CommonException(ErrorCode::INVALID_PARAMS, '待分配客户不在当前数据范围内');
            }
        }
    }
}
