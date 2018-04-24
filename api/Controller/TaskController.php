<?php

namespace Contao\ManagerApi\Controller;

use Contao\ManagerApi\Task\TaskManager;
use Contao\ManagerApi\Task\TaskStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TaskController
{
    /**
     * @var TaskManager
     */
    private $taskManager;

    /**
     * Constructor.
     *
     * @param TaskManager          $taskManager
     */
    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke(Request $request)
    {
        switch ($request->getMethod()) {
            case 'GET':
                return $this->getTask();

            case 'PUT':
                return $this->putTask($request);

            case 'DELETE':
                return $this->deleteTask();
        }

        return new Response(null, Response::HTTP_METHOD_NOT_ALLOWED);
    }

    private function getTask()
    {
        return $this->getResponse($this->taskManager->updateTask());
    }

    private function putTask(Request $request)
    {
        if ($this->taskManager->hasTask()) {
            throw new BadRequestHttpException('A task is already active');
        }

        $name = $request->request->get('name');
        $config = $request->request->get('config', []);

        if (empty($name) || !is_array($config)) {
            throw new BadRequestHttpException('Invalid task data');
        }

        return $this->getResponse($this->taskManager->startTask($name, $config));
    }

    private function deleteTask()
    {
        if (!$this->taskManager->hasTask()) {
            throw new BadRequestHttpException('No active task found.');
        }

        return $this->getResponse($this->taskManager->deleteTask());
    }

    /**
     * @param TaskStatus $status
     * @param int        $code
     *
     * @return Response
     */
    private function getResponse(TaskStatus $status = null, $code = Response::HTTP_OK)
    {
        if (!$status instanceof TaskStatus) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        if (!$status->getDetail()) {
            switch ($status->getStatus()) {
                case TaskStatus::STATUS_COMPLETE:
                    $status->setDetail('The background task was completed successfully. Check the console protocol for the details.');
                    break;

                case TaskStatus::STATUS_ERROR:
                    $status->setDetail('The background task has stopped unexpectedly. Please check the console protocol.');
                    break;
            }
        }

        return new JsonResponse(
            [
                'title' => $status->getTitle(),
                'summary' => $status->getSummary(),
                'detail' => $status->getDetail(),
                'console' => $status->getConsole(),
                'cancellable' => $status->isCancellable(),
                'audit' => $status->hasAudit(),
                'status' => $status->getStatus(),
            ],
            $code
        );
    }
}
