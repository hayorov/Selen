<?php
// Без этой директивы PHP не будет перехватывать сигналы
declare(ticks=1); 

class DaemonClass {
    // Максимальное количество дочерних процессов
    public $maxProcesses,
           $logLevel;
    // Когда установится в TRUE, демон завершит работу
    protected $stop_server = FALSE;
    // Здесь будем хранить запущенные дочерние процессы
    protected $currentJobs = array();

    public function __construct($loglevel='CRIT',$maxProc=10) {
        /* loglevel 0 -CRIT;
                    1 -ALL
                    2 -DEBUG
        */
        
        switch ($loglevel) {
            case 'CRIT': $this->logLevel = 0;
            break;
            case 'ALL': $this->logLevel = 1;
            break;
            case 'DEBUG': $this->logLevel = 2;
            break;
            default: $this->logLevel = 0;
        }
        
        $this->maxProcesses = $maxProc;
        echo date("[h:i:s Y/m/d] ")."Сonstructed daemon controller".PHP_EOL;
        // Ждем сигналы SIGTERM и SIGCHLD
        pcntl_signal(SIGTERM, array($this, "childSignalHandler"));
        pcntl_signal(SIGCHLD, array($this, "childSignalHandler"));
    }
    // функция логирования
    public function addLog($logtext,$loglevel) {
               if ($loglevel <= $this->logLevel) echo date("[H:i:s Y/m/d] ").$logtext.PHP_EOL;  
    }
    public function run() {
        $this->addLog("Running daemon controller",1);
        require_once('classes/storage.php');
        require_once('classes/postprocessor.php');
        $storage = new StorageBase('main.db');
        $this->addLog("Init storage class",1);
        // Пока $stop_server не установится в TRUE, гоняем бесконечный цикл
        while (!$this->stop_server) {
            // Если уже запущено максимальное количество дочерних процессов, ждем их завершения
            while(count($this->currentJobs) >= $this->maxProcesses) {
                 $this->addLog("waiting for free procs",1);
                 sleep(1);
            }
           $qID=$storage->getTaskFromQueue();
           if (is_numeric($qID)){
           $this->addLog('Exec tack with qID='.$qID);
           $this->launchJob($qID);
           }
           else $this->addLog("no jobs",2);
           sleep(2);
        } 
    } 
    
  protected function launchJob($qID) { 
        // Создаем дочерний процесс
        // весь код после pcntl_fork() будет выполняться
        // двумя процессами: родительским и дочерним
        $pid = pcntl_fork();
        if ($pid == -1) {
            // Не удалось создать дочерний процесс
            error_log('Could not launch new job, exiting');
            return FALSE;
        } 
        elseif ($pid) {
            // Этот код выполнится родительским процессом
            $this->currentJobs[$pid] = TRUE;
        } 
        else { 
            // А этот код выполнится дочерним процессом
            $this->addLog("PID=".getmypid().' & qID='.$qID.' created',1);
            $processor = new Processor();
            $result = $processor->execTest($qID);
            $this->addLog("PID=".getmypid().' & qID='.$qID.' died',2);
            exit(); 
        } 
        return TRUE; 
    } 
    
  public function childSignalHandler($signo, $pid = null, $status = null) {
        switch($signo) {
            case SIGTERM:
                // При получении сигнала завершения работы устанавливаем флаг
                $this->stop_server = true;
                break;
            case SIGCHLD:
                // При получении сигнала от дочернего процесса
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG); 
                } 
                // Пока есть завершенные дочерние процессы
                while ($pid > 0) {
                    if ($pid && isset($this->currentJobs[$pid])) {
                        // Удаляем дочерние процессы из списка
                        unset($this->currentJobs[$pid]);
                    } 
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                } 
                break;
            default:
                // все остальные сигналы
        }
    }      
}

?>
