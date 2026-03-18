<?php
namespace OPNsense\GwMonitor\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\GwMonitor\Monitor;

class ServiceController extends ApiControllerBase
{
    /**
     * POST /api/gwmonitor/service/reconfigure
     * Генерирует скрипты из config.xml и перезапускает все мониторы
     */
    public function reconfigureAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'message' => 'POST required'];
        }

        $backend = new Backend();
        $output  = trim($backend->configdRun('gwmonitor reconfigure'));

        if (empty($output)) {
            return ['result' => 'failed', 'message' => 'No response from configd'];
        }

        $failed = stripos($output, 'ERROR') !== false
               || stripos($output, 'failed') !== false;

        return [
            'result'  => $failed ? 'failed' : 'ok',
            'message' => $output,
        ];
    }

    /**
     * GET /api/gwmonitor/service/status
     * Возвращает статус всех инстансов + текущие RTT/Loss
     */
    public function statusAction()
    {
        $backend = new Backend();
        $output  = trim($backend->configdRun('gwmonitor status'));

        if (empty($output)) {
            return ['result' => 'failed', 'message' => 'No response from configd'];
        }

        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['result' => 'failed', 'message' => 'Invalid JSON: ' . $output];
        }

        return $data;
    }

    /**
     * POST /api/gwmonitor/service/startMonitor
     * Запускает конкретный инстанс по uuid
     */
    public function startMonitorAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'message' => 'POST required'];
        }

        $uuid = $this->request->getPost('uuid');
        if (empty($uuid)) {
            return ['result' => 'failed', 'message' => 'uuid required'];
        }

        $backend = new Backend();
        $output  = trim($backend->configdRun('gwmonitor start ' . escapeshellarg($uuid)));

        return [
            'result'  => empty($output) ? 'failed' : 'ok',
            'message' => $output ?: 'No response from configd',
        ];
    }

    /**
     * POST /api/gwmonitor/service/stopMonitor
     * Останавливает конкретный инстанс по uuid
     */
    public function stopMonitorAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'message' => 'POST required'];
        }

        $uuid = $this->request->getPost('uuid');
        if (empty($uuid)) {
            return ['result' => 'failed', 'message' => 'uuid required'];
        }

        $backend = new Backend();
        $output  = trim($backend->configdRun('gwmonitor stop ' . escapeshellarg($uuid)));

        return [
            'result'  => empty($output) ? 'failed' : 'ok',
            'message' => $output ?: 'No response from configd',
        ];
    }
}
