<?php
class Kwf_Events_Dispatcher
{
    public static $_indent = 0;
    private static $_listeners;
    public static $eventsCount = 0;

    public static final function getAllListeners()
    {
        if (!isset(self::$_listeners)) {
            if (!Kwf_Component_Settings::$_rootComponentClassSet && file_exists('build/events/listeners')) {
                self::$_listeners = unserialize(file_get_contents('build/events/listeners'));
            } else {
                self::$_listeners = self::_getAllListeners();
            }
        }

        return self::$_listeners;
    }

    public static function clearCache()
    {
        self::$_listeners = null;
    }

    private static function _getAllListeners()
    {
        $eventObjects = array();
        $hasFulltext = false;
        foreach (Kwc_Abstract::getComponentClasses() as $componentClass) {
            $eventsClass = Kwc_Admin::getComponentClass($componentClass, 'Events');
            $eventObjects[] = Kwf_Component_Abstract_Events::getInstance(
                    $eventsClass, array('componentClass' => $componentClass)
            );
            foreach (Kwc_Abstract::getSetting($componentClass, 'generators') as $generatorKey => $null) {
                $generator = current(Kwf_Component_Generator_Abstract::getInstances(
                        $componentClass, array('generator' => $generatorKey))
                );
                $eventsClass = $generator->getEventsClass();
                if ($eventsClass) {
                    $eventObjects[] = Kwf_Component_Generator_Events::getInstance(
                            $eventsClass,
                            array(
                                    'componentClass' => $componentClass,
                                    'generatorKey' => $generatorKey
                            )
                    );
                }
            }
            if (Kwc_Abstract::getFlag($componentClass, 'usesFulltext')) {
                $hasFulltext = true;
            }
            if (Kwc_Abstract::hasSetting($componentClass, 'menuConfig')) {
                $mc = Kwf_Component_Abstract_MenuConfig_Abstract::getInstance($componentClass);
                $eventsClass = $mc->getEventsClass();
                if ($eventsClass) {
                    $eventObjects[] = Kwf_Component_Abstract_MenuConfig_Events::getInstance(
                            $eventsClass,
                            array(
                                'componentClass' => $componentClass
                            )
                    );
                }
            }
        }
        $eventObjects[] = Kwf_Events_Subscriber::getInstance('Kwf_Component_Events_ViewCache');
        $eventObjects[] = Kwf_Events_Subscriber::getInstance('Kwf_Component_Events_UrlCache');
        $eventObjects[] = Kwf_Events_Subscriber::getInstance('Kwf_Component_Events_ProcessInputCache');
        $eventObjects[] = Kwf_Events_Subscriber::getInstance('Kwf_Component_Events_RequestHttpsCache');
        if ($hasFulltext) {
            $eventObjects[] = Kwf_Events_Subscriber::getInstance('Kwf_Component_Events_Fulltext');
        }

        $listeners = array();
        foreach ($eventObjects as $eventObject) {
            if (get_class($eventObject) == 'Kwf_Component_Generator_Events_Table') {

            }

            foreach ($eventObject->getListeners() as $listener) {
                if (!is_array($listener) ||
                        !isset($listener['event']) ||
                        !isset($listener['callback'])
                ) {
                    throw new Kwf_Exception('Listeners of ' . get_class($eventObject) . ' must return arrays with keys "class" (optional), "event" and "callback"');
                }
                $event = $listener['event'];
                if (!class_exists($event)) throw new Kwf_Exception("Event-Class $event not found, comes from " . get_class($subscriber));
                $class = isset($listener['class']) ? $listener['class'] : 'all';
                if (!is_array($class)) {
                    $class = array($class);
                }
                foreach ($class as $c) {
                    if (is_object($c)) $c = get_class($c);
                    $listeners[$event][$c][] = array(
                            'class' => get_class($eventObject),
                            'method' => $listener['callback'],
                            'config' => $eventObject->getConfig()
                    );
                }
            }
        }
        return $listeners;
    }

    public static function fireEvent($event)
    {
        $logger = Kwf_Events_Log::getInstance();
        if ($logger && $logger->indent == 0) {
            $logger->info('----');
            $logger->resetTimer();
        }


        $class = $event->class;
        $eventClass = get_class($event);

        $cacheId = '-ev-lst-'.Kwf_Component_Data_Root::getComponentClass().'-'.$eventClass.'-'.$class;
        $callbacks = Kwf_Cache_SimpleStatic::fetch($cacheId);
        if ($callbacks === false) {
            $listeners = self::getAllListeners();
            $callbacks = array();
            if ($class && isset($listeners[$eventClass][$class])) {
                $callbacks = $listeners[$eventClass][$class];
            }
            if (isset($listeners[$eventClass]['all'])) {
                $callbacks = array_merge($callbacks, $listeners[$eventClass]['all']);
            }
            Kwf_Cache_SimpleStatic::add($cacheId, $callbacks);
        }

        if ($logger) {
            $logger->info($event->__toString() . ':');
            $logger->indent++;
        }
        static $callbackBenchmark = array();
        foreach ($callbacks as $callback) {
            $ev = call_user_func(
                array($callback['class'], 'getInstance'),
                $callback['class'],
                $callback['config']
            );
            if ($logger) {
                $msg = '-> '.$callback['class'] . '::' . $callback['method'] . '(' . _btArgsString($callback['config']) . ')';
                $logger->info($msg . ':');
                $start = microtime(true);
            }
            $ev->{$callback['method']}($event);
            if ($logger) {
                if (!isset($callbackBenchmark[$callback['class'] . '::' . $callback['method']])) {
                    $callbackBenchmark[$callback['class'] . '::' . $callback['method']] = array(
                        'calls' => 0,
                        'time' => 0
                    );
                }
                $callbackBenchmark[$callback['class'] . '::' . $callback['method']]['calls']++;
                $callbackBenchmark[$callback['class'] . '::' . $callback['method']]['time'] += (microtime(true)-$start)*1000; //ATM includes everything which is missleading
            }
        }
        if ($logger) {
            $logger->indent--;
            if ($logger->indent == 0) {
                foreach ($callbackBenchmark as $cb=>$i) {
                    $logger->info(sprintf("% 3d", $i['calls'])."x ".sprintf("%3d", round($i['time'], 0))." ms: $cb");
                }
                $callbackBenchmark = array();
            }
        }

        self::$eventsCount++;
    }
}