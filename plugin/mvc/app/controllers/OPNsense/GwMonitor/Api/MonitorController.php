<?php
namespace OPNsense\GwMonitor\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class MonitorController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\GwMonitor\Monitor';
    protected static $internalModelName  = 'monitor';

    public function searchMonitorsAction()
    {
        return $this->searchBase(
            'monitors',
            ['enabled', 'gw_name', 'probe_if', 'probe_host', 'probe_port', 'probe_interval', 'description']
        );
    }

    public function getMonitorAction($uuid = null)
    {
        return $this->getBase('monitor', 'monitors', $uuid);
    }

    public function addMonitorAction()
    {
        return $this->addBase('monitor', 'monitors');
    }

    public function setMonitorAction($uuid = null)
    {
        return $this->setBase('monitor', 'monitors', $uuid);
    }

    public function delMonitorAction($uuid = null)
    {
        return $this->delBase('monitors', $uuid);
    }

    public function toggleMonitorAction($uuid = null, $enabled = null)
    {
        return $this->toggleBase('monitors', $uuid, $enabled);
    }
}
