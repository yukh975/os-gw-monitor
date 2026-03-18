<?php
namespace OPNsense\GwMonitor\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\GwMonitor\Monitor;

class MonitorController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\GwMonitor\Monitor';
    protected static $internalModelName  = 'monitor';

    public function searchMonitorsAction()
    {
        return $this->searchBase(
            'monitors',
            ['enabled','gw_name','probe_if','probe_host','probe_port','probe_interval','description']
        );
    }

    public function getMonitorAction($uuid = null)
    {
        return $this->getBase('monitor', 'monitors', $uuid);
    }

    public function addMonitorAction()
    {
        if ($this->request->isPost()) {
            $data = $this->request->getPost('monitor');
            $gw_name = !empty($data['gw_name']) ? trim($data['gw_name']) : '';

            if (!empty($gw_name)) {
                $mdl = $this->getModel();
                foreach ($mdl->monitors->iterateItems() as $uuid => $item) {
                    if ((string)$item->gw_name === $gw_name) {
                        return [
                            'result'  => 'failed',
                            'validations' => [
                                'monitor.gw_name' => 'This gateway is already being monitored.'
                            ]
                        ];
                    }
                }
            }
        }
        return $this->addBase('monitor', 'monitors');
    }

    public function setMonitorAction($uuid = null)
    {
        if ($this->request->isPost() && $uuid !== null) {
            $data = $this->request->getPost('monitor');
            $gw_name = !empty($data['gw_name']) ? trim($data['gw_name']) : '';

            if (!empty($gw_name)) {
                $mdl = $this->getModel();
                foreach ($mdl->monitors->iterateItems() as $item_uuid => $item) {
                    if ($item_uuid === $uuid) continue;
                    if ((string)$item->gw_name === $gw_name) {
                        return [
                            'result'  => 'failed',
                            'validations' => [
                                'monitor.gw_name' => 'This gateway is already being monitored.'
                            ]
                        ];
                    }
                }
            }
        }
        return $this->setBase('monitor', 'monitors', $uuid);
    }

    public function delMonitorAction($uuid = null)
    {
        return $this->delBase('monitors', $uuid);
    }

    public function toggleMonitorAction($uuid = null)
    {
        return $this->toggleBase('monitors', $uuid);
    }
}
