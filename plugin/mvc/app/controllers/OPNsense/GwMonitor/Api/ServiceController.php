<?php
namespace OPNsense\GwMonitor\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\GwMonitor\Monitor;

class ServiceController extends ApiControllerBase
{
    /**
     * POST /api/gwmonitor/service/reconfigure
     * Generates scripts from config.xml and restarts all monitors
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
            'message' => $failed ? 'Reconfiguration failed' : $output,
        ];
    }

    /**
     * GET /api/gwmonitor/service/status
     * Returns the status of all instances + current RTT/Loss
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
            return ['result' => 'failed', 'message' => 'Invalid response from backend'];
        }

        return $data;
    }

    /**
     * POST /api/gwmonitor/service/startMonitor
     * Starts a specific instance by uuid
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
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
            return ['result' => 'failed', 'message' => 'invalid uuid'];
        }

        $backend = new Backend();
        $output  = trim($backend->configdRun('gwmonitor start ' . escapeshellarg($uuid)));
        $ok      = stripos($output, 'OK') !== false;

        return [
            'result'  => $ok ? 'ok' : 'failed',
            'message' => $ok ? $output : 'Failed to start monitor',
        ];
    }

    /**
     * POST /api/gwmonitor/service/stopMonitor
     * Stops a specific instance by uuid
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
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
            return ['result' => 'failed', 'message' => 'invalid uuid'];
        }

        $backend = new Backend();
        $output  = trim($backend->configdRun('gwmonitor stop ' . escapeshellarg($uuid)));
        $ok      = stripos($output, 'OK') !== false;

        return [
            'result'  => $ok ? 'ok' : 'failed',
            'message' => $ok ? $output : 'Failed to stop monitor',
        ];
    }
}
