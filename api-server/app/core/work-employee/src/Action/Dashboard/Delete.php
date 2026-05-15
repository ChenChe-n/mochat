<?php

declare(strict_types=1);
/**
 * This file is part of MoChat.
 * @link     https://mo.chat
 * @document https://mochat.wiki
 * @contact  group@mo.chat
 * @license  https://github.com/mochat-cloud/mochat/blob/master/LICENSE
 */
namespace MoChat\App\WorkEmployee\Action\Dashboard;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use MoChat\App\Common\Middleware\DashboardAuthMiddleware;
use MoChat\App\WorkEmployee\Logic\DeleteLogic;
use MoChat\Framework\Action\AbstractAction;
use MoChat\Framework\Request\ValidateSceneTrait;

/**
 * 企业成员-删除.
 * @Controller
 */
class Delete extends AbstractAction
{
    use ValidateSceneTrait;

    /**
     * @Inject
     * @var DeleteLogic
     */
    protected $deleteLogic;

    /**
     * @Middlewares({
     *     @Middleware(DashboardAuthMiddleware::class)
     * })
     * @RequestMapping(path="/dashboard/workEmployee/delete", methods="delete")
     */
    public function handle(): array
    {
        $this->validated($this->request->all());

        return $this->deleteLogic->handle([
            'employeeId' => $this->request->input('employeeId'),
        ], user());
    }

    protected function rules(): array
    {
        return [
            'employeeId' => 'required | integer | min:1 | bail',
        ];
    }

    protected function messages(): array
    {
        return [
            'employeeId.required' => '成员ID 必填',
            'employeeId.integer' => '成员ID 必需为整数',
            'employeeId.min' => '成员ID 不可小于1',
        ];
    }
}
