<?php
namespace MobesAi\Api\Middleware;
class RateLimiter {
    private string $key;
    private int $limit;
    private string $file;
    public function __construct(string $sessionId,int $limit){
        $this->key=$sessionId?:'guest';
        $this->limit=$limit;
        $this->file=sys_get_temp_dir().'/mobes_ai_rate_'.$this->key;
    }
    public function check(){
        $now=time();
        if(!file_exists($this->file)){
            file_put_contents($this->file,json_encode(['t'=>$now,'c'=>1]));
            return true;
        }
        $data=json_decode(file_get_contents($this->file),true);
        if(!$data){$data=['t'=>$now,'c'=>0];}
        // 60 秒経過でリセット
        if($now-$data['t']>60){$data=['t'=>$now,'c'=>1];}
        else{$data['c']++;}
        file_put_contents($this->file,json_encode($data));
        return $data['c']<=$this->limit;
    }
} 