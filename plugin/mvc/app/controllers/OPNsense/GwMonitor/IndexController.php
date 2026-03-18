<?php
namespace OPNsense\GwMonitor;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->formDialogMonitor = $this->getForm('dialogMonitor');
        $this->view->formGridMonitors  = $this->getFormGrid('dialogMonitor');
        $this->view->pick('OPNsense/GwMonitor/index');
    }
}
