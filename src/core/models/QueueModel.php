<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;
use stdClass;

require_once(dirname(__FILE__)."/../Database.php");
require_once(dirname(__FILE__)."/PersonModel.php");

class QueueModel extends Database{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PULLED = 'PULLED';
    public const STATUS_PROCESS = 'PROCESS';
    public const STATUS_COMPLETED= 'COMPLETED';

    public function __construct(){
        parent::__construct();
    }

    public function list(): array{
        $queues = $this->query("
            select * 
            from queue 
            order by created_at
        ");

        $queues = json_decode(json_encode($queues), true);
        $results = [];
        foreach($queues as $row){
            try{
                $row['queue_code'] = $this->getQueueCode($row);
                $row['person'] = (new PersonModel())->get($row['nik']);

                $results []= $row;
            }catch(\Exception $e){
                if($e->getMessage() != 'Data not found'){
                    throw $e;
                }
            }
        }

        $results = json_decode(json_encode($results));

        return $results;
    }

    public function add($prefix, $nik): stdClass{
        $queue_no = 0;

        $queues = $this->queryPrepared(
            'SELECT * FROM queue WHERE queue_prefix = ? ORDER BY queue_no DESC LIMIT 1',
            [$prefix]
        );

        if(count($queues)>0){
            $queue_no = intval($queues[0]->queue_no);
        }
        
        $queue_no++;

        $res = $this->executePrepared(
            'INSERT INTO queue (queue_prefix, queue_no, nik, status) VALUES (?, ?, ?, ?)',
            [$prefix, (string) $queue_no, $nik, self::STATUS_PENDING]
        );

        $lastId = $this->getLastInsertedId();

        $queues = $this->queryPrepared('SELECT * FROM queue WHERE queue_id = ?', [(string) $lastId]);

        return count($queues)>0? $queues[0]: null;
    }

    public function find($queue_id){
        $queues = $this->queryPrepared('SELECT * FROM queue WHERE queue_id = ?', [(string) $queue_id]);

        if(count($queues)>0){
            $result = json_decode(json_encode($queues[0]), true);
            $result['queue_code'] = $this->getQueueCode($result);
            $result['person'] = (new PersonModel())->get($result['nik']);
            $result = json_decode(json_encode($result));

            return $result;
        }

        return null;
    }

    public function findByNik($nik, $status = []){
        $sql = 'SELECT * FROM queue WHERE nik = ?';
        $params = [$nik];
        if(count($status)>0){
            $allowedStatuses = [
                self::STATUS_PENDING,
                self::STATUS_PULLED,
                self::STATUS_PROCESS,
                self::STATUS_COMPLETED,
            ];
            $status = array_values(array_intersect($status, $allowedStatuses));
            if ($status !== []) {
                $sql .= ' AND status IN (' . implode(',', array_fill(0, count($status), '?')) . ')';
                $params = array_merge($params, $status);
            }
        }
        $queues = $this->queryPrepared($sql, $params);

        if(count($queues)>0){
            $result = json_decode(json_encode($queues[0]), true);
            $result['queue_code'] = $this->getQueueCode($result);
            $result['person'] = (new PersonModel())->get($result['nik']);
            $result = json_decode(json_encode($result));

            return $result;
        }

        return null;
    }

    public function pullQueue(string $prefix){
        $queues = $this->queryPrepared(
            'SELECT * FROM queue
             WHERE queue_prefix = ? AND status = ?
             ORDER BY created_at ASC, updated_at DESC LIMIT 1',
            [$prefix, self::STATUS_PENDING]
        );

        $result = null;

        if(count($queues)>0){
            $result = json_decode(json_encode($queues[0]), true);
            $result['queue_code'] = $this->getQueueCode($result);
            $result['person'] = (new PersonModel())->get($result['nik']);
            $result = json_decode(json_encode($result));

            $res = $this->updateStatus($result->queue_id, self::STATUS_PULLED);
        }

        return $result;
    }

    public function process($queue_id): bool{
        $queues = $this->queryPrepared(
            'SELECT * FROM queue WHERE queue_id = ? AND status = ? LIMIT 1',
            [(string) $queue_id, self::STATUS_PULLED]
        );

        if(count($queues)>0){
            $res = $this->updateStatus($queue_id, self::STATUS_PROCESS);

            return $res;
        }

        return false;
    }

    public function complete($queue_id){
        return $this->updateStatus($queue_id, self::STATUS_COMPLETED);
    }

    public function reEnqueue($queue_id){
        return $this->updateStatus($queue_id, self::STATUS_PENDING);
    }

    public function updateStatus($queue_id, $status){
        $allowedStatuses = [
            self::STATUS_PENDING,
            self::STATUS_PULLED,
            self::STATUS_PROCESS,
            self::STATUS_COMPLETED,
        ];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid queue status');
        }
        $res = $this->executePrepared(
            'UPDATE queue SET status = ? WHERE queue_id = ?',
            [$status, (string) $queue_id]
        );

        return $res;
    }

    private function getQueueCode($queue){
        if(!empty($queue)){
            $queue = json_decode(json_encode($queue));
            $code = $queue->queue_prefix.sprintf('%04d', $queue->queue_no);

            return $code;
        }

        return null;
    }

    private function removesCompleted(){
        $res = $this->execute("
            delete from queue
            where
                status = '".self::STATUS_COMPLETED."'
        ");

        return $res;
    }
}
