<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\WorkDepartment\Action\Dashboard;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use MoChat\App\WorkDepartment\Contract\WorkDepartmentContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeContract;
use MoChat\App\WorkEmployee\Contract\WorkEmployeeDepartmentContract;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 成员部门管理-企业微信成员下拉列表.
 *
 * Class SelectEmployee.
 * @Controller
 */
class SelectEmployee extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var WorkDepartmentContract
     */
    protected $workDepartmentService;

    /**
     * @Inject
     * @var WorkEmployeeDepartmentContract
     */
    protected $workEmployeeDepartmentService;

    /**
     * @Inject
     * @var WorkEmployeeContract
     */
    protected $workEmployeeService;

    /**
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class)
     * })
     * @RequestMapping(path="/dashboard/workDepartment/selectEmployee", methods="get")
     * @return array 返回数组
     */
    public function handle(): array
    {
        ## 参数验证
        $this->validated($this->request->all());
        ## 获取当前登录用户
        $user = user();
        $corpId = (int) $user['corpIds'][0];
        $name = $this->request->input('name', '');

        ## 获取当前企业已同步的企业微信成员
        $employeeList = $this->workEmployeeService->getWorkEmployeesByCorpIdWxUserIdNotNull(
            [$corpId],
            ['id', 'name', 'wx_user_id', 'log_user_id', 'mobile', 'status']
        );
        if (empty($employeeList)) {
            return [];
        }
        if ($name !== '') {
            $employeeList = array_filter($employeeList, static function ($employee) use ($name) {
                return strpos($employee['name'], $name) !== false || strpos($employee['wxUserId'], $name) !== false;
            });
        }

        $employeeDepartments = $this->employeeDepartments(array_column($employeeList, 'id'));
        return array_values(array_map(static function ($employee) use ($employeeDepartments) {
            return [
                'employeeId' => $employee['id'],
                'employeeName' => $employee['name'],
                'wxUserId' => $employee['wxUserId'],
                'logUserId' => $employee['logUserId'],
                'phone' => $employee['mobile'],
                'status' => $employee['status'],
                'department' => $employeeDepartments[$employee['id']] ?? [],
            ];
        }, $employeeList));
    }

    /**
     * 验证规则.
     *
     * @return array 响应数据
     */
    protected function rules(): array
    {
        return [
            'name' => 'string | bail',
        ];
    }

    /**
     * 验证错误提示.
     * @return array 响应数据
     */
    protected function messages(): array
    {
        return [
            'name.string' => '成员名称 必需为字符串',
        ];
    }

    private function employeeDepartments(array $employeeIds): array
    {
        if (empty($employeeIds)) {
            return [];
        }
        $employeeDepartmentList = $this->workEmployeeDepartmentService->getWorkEmployeeDepartmentsByEmployeeIds($employeeIds, ['employee_id', 'department_id']);
        if (empty($employeeDepartmentList)) {
            return [];
        }
        $departmentList = $this->workDepartmentService->getWorkDepartmentsById(array_unique(array_column($employeeDepartmentList, 'departmentId')), ['id', 'name']);
        if (empty($departmentList)) {
            return [];
        }
        $departmentList = array_column($departmentList, null, 'id');

        $data = [];
        foreach ($employeeDepartmentList as $employeeDepartment) {
            if (empty($departmentList[$employeeDepartment['departmentId']])) {
                continue;
            }
            $data[$employeeDepartment['employeeId']][] = [
                'departmentId' => $employeeDepartment['departmentId'],
                'departmentName' => $departmentList[$employeeDepartment['departmentId']]['name'],
            ];
        }

        return $data;
    }
}
