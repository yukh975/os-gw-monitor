<script>
$(document).ready(function () {

    $("#{{formGridMonitors['table_id']}}").UIBootgrid({
        'search':  '/api/gwmonitor/monitor/searchMonitors/',
        'get':     '/api/gwmonitor/monitor/getMonitor/',
        'set':     '/api/gwmonitor/monitor/setMonitor/',
        'add':     '/api/gwmonitor/monitor/addMonitor/',
        'del':     '/api/gwmonitor/monitor/delMonitor/',
        'toggle':  '/api/gwmonitor/monitor/toggleMonitor/',
        'options': {selection: false, multiSelect: false}
    });

    $("#reconfigureAct").SimpleActionButton();

});
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#monitors">{{ lang._('Monitors') }}</a></li>
</ul>

<div style="padding: 6px 15px; font-size: 12px; color: var(--color-text-secondary, #888); border-bottom: 1px solid #eee;">
    os-gw-monitor v1.0.7
</div>
<div class="tab-content content-box">
    <div id="monitors" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridMonitors) }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/gwmonitor/service/reconfigure'}) }}

{{ partial("layout_partials/base_dialog", ['fields': formDialogMonitor, 'id': formGridMonitors['edit_dialog_id'], 'label': lang._('Edit Gateway Monitor')]) }}
