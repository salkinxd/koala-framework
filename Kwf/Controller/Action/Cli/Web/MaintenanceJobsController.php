<?php
class Kwf_Controller_Action_Cli_Web_MaintenanceJobsController extends Kwf_Controller_Action_Cli_Abstract
{
    public static function getHelp()
    {
        return "execute mainteanance commands, should be run by process-control";
    }

    public function runAction()
    {
        $debug = $this->_getParam('debug');

        if (file_exists('temp/shutdown-maintenance')) {
            unlink('temp/shutdown-maintenance');
        }

        $lastDailyRun = null;
        if (file_exists('temp/maintenance-daily-run')) {
            $lastDailyRun = file_get_contents('temp/maintenance-daily-run');
            if ($debug) echo "last daily run: ".date('Y-m-d H:i:s', $lastDailyRun)."\n";
        }


        $dailyMaintenanceWindowStart = "01:00"; //don't set before 00:00
        $dailyMaintenanceWindowEnd = "05:00";

        $nextDailyRun = null;
        $lastMinutelyRun = null;
        while (true) {
            if (!$nextDailyRun) {
                if ($lastDailyRun && $lastDailyRun > strtotime($dailyMaintenanceWindowStart)) { //today already run
                    //maintenance window of tomorrow
                    $nextDailyRun = rand(strtotime("tomorrow $dailyMaintenanceWindowStart"), strtotime("tomorrow $dailyMaintenanceWindowEnd"));
                } else { //not yet run or today not yet run
                    if (time() < strtotime($dailyMaintenanceWindowEnd)) { //window not yet over for today
                        //maintenance window of today
                        $nextDailyRun = rand(max(time(), strtotime($dailyMaintenanceWindowStart)), strtotime($dailyMaintenanceWindowEnd));
                    } else {
                        //maintenance window of tomorrow
                        $nextDailyRun = rand(strtotime("tomorrow $dailyMaintenanceWindowStart"), strtotime("tomorrow $dailyMaintenanceWindowEnd"));
                    }
                }
                if ($debug) echo "Next daily run: ".date('Y-m-d H:i:s', $nextDailyRun)."\n";
            }

            Kwf_Util_Maintenance_Dispatcher::executeJobs(Kwf_Util_Maintenance_Job_Abstract::FREQUENCY_SECONDS, $debug);
            if (!$lastMinutelyRun || time()-$lastMinutelyRun > 60) {
                $lastMinutelyRun = time();
                Kwf_Util_Maintenance_Dispatcher::executeJobs(Kwf_Util_Maintenance_Job_Abstract::FREQUENCY_MINUTELY, $debug);
            }

            Kwf_Component_Data_Root::getInstance()->freeMemory();

            if (time() > $nextDailyRun) {
                if ($debug) echo date('Y-m-d H:i:s')." execute daily jobs\n";
                $lastDailyRun = time();
                file_put_contents('temp/maintenance-daily-run', $lastDailyRun);
                $nextDailyRun = null;
                Kwf_Util_Maintenance_Dispatcher::executeJobs(Kwf_Util_Maintenance_Job_Abstract::FREQUENCY_DAILY, $debug);
            }
            sleep(10);
        }
    }

    public function runDailyAction()
    {
        $debug = $this->_getParam('debug');
        Kwf_Util_Maintenance_Dispatcher::executeJobs(Kwf_Util_Maintenance_Job_Abstract::FREQUENCY_DAILY, $debug);
        exit;
    }
}