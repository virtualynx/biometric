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

        foreach($queues as &$row){
            $row['queue_code'] = $this->getQueueCode($row);
            $row['person'] = (new PersonModel())->get($row['nik']);

            unset($row);
        }

        $queues = json_decode(json_encode($queues));

        return $queues;
    }

    public function add($prefix, $nik): stdClass{
        $queue_no = 0;

        $queues = $this->query("
            select * 
            from queue 
            where queue_prefix = '$prefix'
            order by queue_no DESC 
            limit 1"
        );

        if(count($queues)>0){
            $queue_no = intval($queues[0]->queue_no);
        }
        
        $queue_no++;

        $res = $this->execute("
            insert into queue(
                queue_prefix,
                queue_no,
                nik,
                status
            )
            values(
                '$prefix',
                $queue_no,
                '$nik',
                '".self::STATUS_PENDING."'
            )
        ");

        $lastId = $this->getLastInsertedId();

        $queues = $this->query("
            select * 
            from queue 
            where queue_id = $lastId
        ");

        return count($queues)>0? $queues[0]: null;
    }

    public function find($queue_id){
        $queues = $this->query("
            select * 
            from queue 
            where queue_id = $queue_id
        ");

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
        $where_status = '';
        if(count($status)>0){
            $where_status = " and status in ('".implode("', '", $status)."')";
        }
        $queues = $this->query("
            select * 
            from queue 
            where 
                nik = '$nik'
                $where_status
        ");

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
        $queues = $this->query("
            select * 
            from queue 
            where 
                queue_prefix = '$prefix'
                and status = '".self::STATUS_PENDING."'
            order by 
                created_at asc,
                updated_at desc
            limit 1
        ");

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
        $queues = $this->query("
            select * 
            from queue 
            where 
                queue_id = '$queue_id'
                and status = '".self::STATUS_PULLED."'
            limit 1
        ");

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
        $res = $this->execute("
            update queue
            set
                status = '$status'
            where
                queue_id = $queue_id
        ");

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