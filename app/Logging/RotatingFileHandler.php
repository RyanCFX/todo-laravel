<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;

class RotatingFileHandler extends StreamHandler
{
    protected $maxFileSize;
    protected $maxTime;
    protected $currentFile;
    protected $currentFileSize;
    protected $currentFileTime;
    protected $currentFileNumber;
    protected $isEnabled;
    protected $baseFileName;

    public function __construct($stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false, $maxFileSize = 10485760, $maxTime = 18000)
    {
        $this->baseFileName = $stream;
        $this->currentFile = $this->getCurrentFileName();
        parent::__construct($this->currentFile, $level, $bubble, $filePermission, $useLocking);
        
        $this->maxFileSize = $maxFileSize;
        $this->maxTime = $maxTime;
        $this->currentFileSize = file_exists($this->currentFile) ? filesize($this->currentFile) : 0;
        $this->currentFileTime = file_exists($this->currentFile) ? filemtime($this->currentFile) : time();
        $this->currentFileNumber = $this->getCurrentFileNumber();
        $this->isEnabled = env('LOGGING_ENABLED', true);
    }

    protected function write(LogRecord $record): void
    {
        if (!$this->isEnabled) {
            return;
        }

        if ($this->shouldRotate()) {
            $this->rotate();
        }

        $this->writeToJsonArray($record);
        $this->currentFileSize = filesize($this->currentFile);
    }

    protected function writeToJsonArray(LogRecord $record): void
    {
        $logData = $record->toArray();
        
        if (!file_exists($this->currentFile)) {
            file_put_contents($this->currentFile, json_encode([$logData], JSON_PRETTY_PRINT));
            return;
        }

        $content = file_get_contents($this->currentFile);
        $logs = json_decode($content, true) ?: [];
        $logs[] = $logData;
        
        file_put_contents($this->currentFile, json_encode($logs, JSON_PRETTY_PRINT));
    }

    protected function shouldRotate(): bool
    {
        return $this->currentFileSize >= $this->maxFileSize || 
               (time() - $this->currentFileTime) >= $this->maxTime;
    }

    protected function getCurrentFileNumber(): int
    {
        $pattern = $this->getBaseFileName() . '_*.json';
        $files = glob($pattern);
        return count($files) + 1;
    }

    protected function getBaseFileName(): string
    {
        $date = date('Y-m-d');
        return dirname($this->baseFileName) . '/' . basename($this->baseFileName, '.json') . '_' . $date;
    }

    protected function getCurrentFileName(): string
    {
        return $this->getBaseFileName() . '_' . $this->getCurrentFileNumber() . '.json';
    }

    protected function rotate(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->currentFileNumber++;
        $this->currentFile = $this->getCurrentFileName();
        $this->stream = fopen($this->currentFile, 'a');
        $this->currentFileSize = 0;
        $this->currentFileTime = time();
    }
} 