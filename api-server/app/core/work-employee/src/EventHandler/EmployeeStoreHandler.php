<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\WorkEmployee\EventHandler;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Db;
use MoChat\App\Corp\Contract\CorpContract;
use MoChat\App\WorkDepartment\Contract\WorkDepartmentContract;
use MoChat\App\WorkEmployee\Constants\ContactAuth;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeDepartmentContract;
use MoChat\Framework\Annotation\WeChatEventHandler;
use MoChat\Framework\WeWork\EventHandler\AbstractEventHandler;
use MoChat\Framework\WeWork\WeWork;

/**
 * 成员新增 - 事件回调.
 * @WeChatEventHandler(eventPath="event/change_contact/create_user")
 * Class EmployeeStoreHandler
 */
class EmployeeStoreHandler extends AbstractEventHandler
{
    /**
     * @var WorkEmployeeContract;
     */
    protected $workEmployeeService;

    /**
     * @var CorpContract
     */
    protected $corpService;

    /**
     * @var WeWork
     */
    protected $client;

    /**
     * @var WorkEmployeeDepartmentContract
     */
    protected $workEmployeeDepartmentService;

    /**
     * @var WorkDepartmentContract
     */
    protected $workDepartmentService;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function process(): string
    {
        $this->logger = make(StdoutLoggerInterface::class);
        if (empty($this->message)) {
            $this->logger->error('EmployeeStoreHandler->process同步新增成员message不能为空');
            return 'success';
        }
        $this->workEmployeeService = make(WorkEmployeeContract::class);
        $this->corpService = make(CorpContract::class);
        $this->workDepartmentService = make(WorkDepartmentContract::class);
        $this->workEmployeeDepartmentService = make(WorkEmployeeDepartmentContract::class);
        $this->client = make(WeWork::class);
        //获取公司corpId
        $corpIds = $this->getCorpId();
        if (empty($corpIds)) {
            $this->logger->error('EmployeeStoreHandler->process同步新增成员corp不能为空');
            return 'success';
        }
        //异步通知只返回UserID和Department, 其他信息调取接口获取
        $this->getUserInfo((int)$corpIds[0]);
        //成员基础信息
        $createEmployeeData = $this->createEmployeeData($corpIds);
        if (empty($createEmployeeData)) {
            $this->logger->error('EmployeeStoreHandler->process同步新增成员employeeData已存在');
            return 'success';
        }
        //获取成员和部门关系数据
        $employeeDepartmentData = $this->getEmployeeDepartmentData($corpIds);
        //成员主部门
        $createEmployeeData['main_department_id'] = 0;
        if (! empty($this->message['MainDepartment']) && ! empty($employeeDepartmentData['department'])) {
            $createEmployeeData['main_department_id'] = ! empty($employeeDepartmentData['department'][$this->message['MainDepartment']]) ? $employeeDepartmentData['department'][$this->message['MainDepartment']] : 0;
        }
        //开启事务
        Db::beginTransaction();
        try {
            // 新增成员
            $employeeId = $this->workEmployeeService->createWorkEmployee($createEmployeeData);
            // 添加成员部门关系
            if (! empty($employeeDepartmentData['createEmployeeDepartment'])) {
                foreach ($employeeDepartmentData['createEmployeeDepartment'] as $eddk => $eddv) {
                    $employeeDepartmentData['createEmployeeDepartment'][$eddk]['employee_id'] = $employeeId;
                }
                $this->workEmployeeDepartmentService->createWorkEmployeeDepartments($employeeDepartmentData['createEmployeeDepartment']);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->logger->error(sprintf('%s [%s] %s', 'EmployeeStoreHandler->process成员新增异常', date('Y-m-d H:i:s'), $e->getMessage()));
        }
        return 'success';
    }

    /**
     * 获取公司corpId.
     */
    protected function getCorpId(): array
    {
        $corpData = $this->corpService->getCorpsByWxCorpId($this->message['ToUserName'], ['id']);
        if (empty($corpData)) {
            return [];
        }
        return [$corpData['id']];
    }

    /**
     * 成员基础信息.
     * @param $corpId
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \JsonException
     * @throws \League\Flysystem\FileExistsException
     */
    protected function createEmployeeData($corpId): array
    {
        $this->logger->error(sprintf('新增成员信息::[%s]', json_encode($this->message, JSON_THROW_ON_ERROR)));
        //成员基础信息
        $employeeData = $this->workEmployeeService->getWorkEmployeesByCorpIdsWxUserId($corpId, [$this->message['UserID']]);
        if (! empty($employeeData)) {
            return [];
        }
        //头像处理
        $avatar = $this->getAvatar();
        //外部联系人权限
        $contactAuth = $this->getContactAuth($this->message['ToUserName']);
        return [
            'corp_id' => $corpId[0],
            'wx_user_id' => $this->message['UserID'],
            'name' => ! empty($this->message['Name']) ? $this->message['Name'] : '',
            'mobile' => ! empty($this->message['Mobile']) ? $this->message['Mobile'] : '',
            'position' => ! empty($this->message['Position']) ? $this->message['Position'] : '',
            'gender' => ! empty($this->message['Gender']) ? $this->message['Gender'] : 0,
            'email' => ! empty($this->message['Email']) ? $this->message['Email'] : '',
            'avatar' => ! empty($avatar['avatar']) ? $avatar['avatar'] : '',
            'thumb_avatar' => ! empty($avatar['thumbAvatar']) ? $avatar['thumbAvatar'] : '',
            'telephone' => ! empty($this->message['Telephone']) ? $this->message['Telephone'] : '',
            'alias' => ! empty($this->message['Alias']) ? $this->message['Alias'] : '',
            'extattr' => ! empty($this->message['ExtAttr']) ? json_encode($this->message['ExtAttr']) : json_encode([]),
            'external_profile' => ! empty($this->message['external_profile']) ? json_encode($this->message['external_profile']) : json_encode([]),
            'external_position' => ! empty($this->message['external_position']) ? json_encode($this->message['external_position']) : json_encode([]),
            'status' => ! empty($this->message['Status']) ? $this->message['Status'] : '',
            'address' => ! empty($this->message['Address']) ? $this->message['Address'] : '',
            'wx_main_department_id' => ! empty($this->message['MainDepartment']) ? $this->message['MainDepartment'] : '',
            'log_user_id' => 0,
            'contact_auth' => $contactAuth,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 获取成员和部门关系数据.
     * @param $corpId
     */
    protected function getEmployeeDepartmentData($corpId): array
    {
        $createEmployeeDepartment = [];
        $departmentData = $this->workDepartmentService->getWorkDepartmentsByCorpIds($corpId, ['id', 'wx_department_id']);
        if (empty($departmentData)) {
            return [];
        }
        foreach ($departmentData as $dk => $dv) {
            $department[$dv['wxDepartmentId']] = $dv['id'];
        }
        //部门
        $isLeaderInDept = ! empty($this->message['IsLeaderInDept']) ? $this->message['IsLeaderInDept'] : '';
        $orders = ! empty($this->message['Order']) ? $this->message['Order'] : '';
        if (! empty($this->message['Department'])) {
            $wxDepartment = explode(',', $this->message['Department']);
            foreach ($wxDepartment as $wxk => $wxv) {
                //绑定成员和部门关系
                $createEmployeeDepartment[] = [
                    'employee_id' => 0,
                    'is_leader_in_dept' => ! empty($isLeaderInDept[$wxk]) ? $isLeaderInDept[$wxk] : 0,
                    'department_id' => ! empty($department[$wxv]) ? $department[$wxv] : 0,
                    'order' => ! empty($orders[$wxk]) ? $orders[$wxk] : 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        return ['department' => $department, 'createEmployeeDepartment' => $createEmployeeDepartment];
    }

    /**
     * 获取联系人配置权限.
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @return int
     */
    protected function getContactAuth(string $wxUserId)
    {
        //配置联系权限
        $followUser = $this->client->provider('externalContact')->app()->external_contact->getFollowUsers();
        if (empty($followUser['errcode']) && ! empty($followUser['follow_user'])) {
            foreach ($followUser['follow_user'] as $fk => $fv) {
                if ($wxUserId == $fv) {
                    return ContactAuth::YES;
                }
            }
        }
        return ContactAuth::NO;
    }

    /**
     * 头像处理.
     * @throws \League\Flysystem\FileExistsException
     * @return array
     */
    protected function getAvatar()
    {
        if (! empty($this->message['Avatar'])) {
//            $pathAvatarFileName = 'employee/avatar_' . strval(microtime(true) * 10000) . '_' . uniqid() . '.png';
            $thumbAvatar = $this->message['Avatar'];
            if (strpos($this->message['Avatar'], '/0') !== false) {
                $thumbAvatar = substr($this->message['Avatar'], 0, strpos($this->message['Avatar'], '/0')) . '/100';
            }
//            $pathThumAvatarFileName = 'employee/thumb_avatar_' . strval(microtime(true) * 10000) . '_' . uniqid() . '.png';
//            $ossData                = [
//                [$this->message['Avatar'], $pathAvatarFileName],
//                [$thumbAvatar, $pathThumAvatarFileName],
//            ];
//            file_upload_queue($ossData);
            return ['avatar' => $this->message['Avatar'], 'thumbAvatar' => $thumbAvatar];
        }
        return ['avatar' => '', 'thumbAvatar' => ''];
    }

    /**
     * 获取用户信息
     * @param int $corpId
     */
    protected function getUserInfo(int $corpId)
    {
        $corp = $this->corpService->getCorpById($corpId, ['wx_corpid', 'employee_secret']);
        $userInfo = $this->client->provider('user')->app([
            'corp_id' => $corp['wxCorpid'],
            'secret' => $corp['employeeSecret'],
        ])->user->get($this->message['UserID']);
        $this->logger->info($this->message['UserID'] . '用户信息' . json_encode($userInfo, JSON_UNESCAPED_UNICODE));
        if ($userInfo['errcode'] == 0) {
            $this->message['Avatar'] = $userInfo['avatar'] ?? '';
            $this->message['Order'] = $userInfo['order'] ?? [];
            $this->message['IsLeaderInDept'] = $userInfo['is_leader_in_dept'] ?? [];
            $this->message['MainDepartment'] = $userInfo['main_department'] ?? '';
            $this->message['Address'] = $userInfo['address'] ?? '';
            $this->message['Status'] = $userInfo['status'] ?? '';
            $this->message['ExtAttr'] = $userInfo['extattr'] ?? '';
            $this->message['Alias'] = $userInfo['alias'] ?? '';
            $this->message['Email'] = $userInfo['email'] ?? '';
            $this->message['Gender'] = $userInfo['gender'] ?? '';
            $this->message['Position'] = $userInfo['position'] ?? '';
            $this->message['Mobile'] = $userInfo['mobile'] ?? '';
            $this->message['Name'] = $userInfo['name'] ?? '';
        }
    }
}
