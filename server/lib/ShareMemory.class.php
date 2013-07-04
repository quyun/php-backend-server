<?php

/**
 * 共享内存操作类
 */
class SharedMemory
{
        
        private $shm_key = NULL;        // 共享内存KEY
        private $shm_size = NULL;       // 共享内存大小
        private $shm_id = NULL;         // 共享内存ID
        
        private $sem = NULL;            // 信号量
        private $sem_key = NULL;        // 信号KEY

        /*
         * 初始化共享内存对象
         *
         * @param string $shm_name 共享内存段名字
         * @param int $shm_size 共享内存段大小，默认为10M
         */
        public function __construct($shm_name, $shm_size=10485760)
        {
            $this->shm_key = $this->_gen_key($shm_name);
            $this->shm_size = $shm_size;

            $this->sem_key = $this->_gen_key($shm_name.'_lock');
        }

        /*
         * 连接到共享内存
         *
         * @return bool 成功TRUE,失败FALSE
         */
        public function attach()
        {
            try {
                $this->shm_id = shm_attach($this->shm_key, $this->shm_size);

                // 初始化信号量
                $this->sem = sem_get($this->sem_key, 1);
            } catch (Exception $e) {
                return FALSE;
            }

            return TRUE;
        }

        /*
         * 从共享内存断开
         *
         * @return bool 成功TRUE,失败FALSE
         */
        public function detach()
        {
            shm_detach($this->shm_id);
            return TRUE;
        }

        /*
         * 删除共享内存
         *
         * @return bool 成功TRUE,失败FALSE
         */
        public function remove()
        {
            shm_remove($this->shm_id);
            return TRUE;
        }
        
        /*
         * 将变量名及对应值写入共享内存中
         *
         * @param string $varname 变量名
         * @param string $value 变量值
         * @param bool $autolock 是否自动锁定
         *
         * @return bool 写入成功TRUE,失败FALSE
         */
        public function put_var($varname, $value, $autolock=FALSE)
        {
            $varkey = $this->_gen_key($varname);
            if ($autolock) sem_acquire($this->sem);
            $result = shm_put_var($this->shm_id, $varkey, $value);
            if ($autolock) sem_release($this->sem);
            return $result;

            // 写入失败：空间不够或其它异常，删除共内存中所有值
            //if (!shm_remove($this->shm_id)) return FALSE;
            //return shm_put_var($this->shm_id, $varkey, $value);
        }

        /*
         * 从共享内存中取出变量值
         *
         * @param string $varname 变量名
         *
         * @return mixed 变量名对应的值，失败返回FALSE
         */
        public function get_var($varname)
        {
            $varkey = $this->_gen_key($varname);
            $result = shm_get_var($this->shm_id, $varkey);
            return $result;
        }

        /*
         * 检查共享内存中是否存在某变量
         *
         * @param string $varname 变量名
         *
         * @return bool 存在返回TRUE，不存在返回FALSE
         */
        public function has_var($varname)
        {
            $varkey = $this->_gen_key($varname);
            return shm_has_var($this->shm_id, $varkey);
        }

        /*
         * 从共享内存中删除变量
         *
         * @param string $varname 变量名
         * @param bool $autolock 是否自动锁定
         *
         * @return mixed 删除成功TRUE,失败FALSE
         */
        public function remove_var($varname, $autolock=FALSE)
        {
            $varkey = $this->_gen_key($varname);
            if ($autolock) sem_acquire($this->sem);
            if (!shm_has_var($this->shm_id, $varkey)) return TRUE;
            $result = shm_remove_var($this->shm_id, $varkey);
            if ($autolock) sem_release($this->sem);
            return $result;
        }

        /*
         * 锁定共享内存
         */
        public function lock()
        {
            return sem_acquire($this->sem);
        }

        /*
         * 解除共享内存锁定
         */
        public function unlock()
        {
            return sem_release($this->sem);
        }
        
        /*
         * 生成指定字符串对应的键
         *
         * @param string $name 字符串
         *
         * @return int 键
         */
        private function _gen_key($str)
        {
            // 假设碰撞机率比较低
            return hexdec(substr(md5($str), 8, 8));
        }
}