<?

require('plugins/redis-wp-cache/predis/PHP5.2/lib/Predis.php');
define(__REDIS_OBJECT_CACHE_INSTALLED, True);

function wp_cache_init() {
	global $wp_object_cache;
    $wp_object_cache = new WP_Object_Cache();
}

function wp_cache_add($key,$data,$group='default',$expire=0){
    global $wp_object_cache;
    return $wp_object_cache->set($key,$data,$group,$expire);
}

function wp_cache_set($key, $data, $flag='',$expire = 0){

	global $wp_object_cache;

	if ( defined('WP_INSTALLING') == false )
		return $wp_object_cache->set($key, $data, $flag, $expire);
	else
		return $wp_object_cache->delete($key, $flag);
}

function wp_cache_get($key,$group = 'default', $expire = 0){
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $expire);
}

function wp_cache_delete($id, $group){
    global $wp_object_cache;
    return $wp_object_cache->delete($id, $group);
}

function wp_cache_replace($key, $data, $group = 'default', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->replace($key,$data,$group,$expire);
}

function wp_cache_flush(){
    global $wp_object_cache;
    return $wp_object_cache->flushdb();
}

function wp_cache_close(){

}


class WP_Object_Cache {
    var $global_groups = array ('users', 'userlogins', 'usermeta', 'site-options', 'site-lookup', 'blog-lookup', 'blog-details', 'rss');
	var $no_redis_groups = array( 'comment', 'counts' );
    var $tmp_cache = array();
    var $debug = false;
    var $blog_id;
    var $redis;
    var $redis_servers = array('host'     => '127.0.0.1', 
                               'port'     => 6379, 
                               'database' => 1,
                              );  
    
    function WP_Object_Cache(){
        global $blog_id;
        $this->blog_id = $blog_id;    
        $this->redis = new redis_wp_cache();
        $this->redis->connect($this->redis_servers);
    }
    
    
    function key($key, $group) {	
		if ( empty($group) )
			$group = 'default';
		if (false !== array_search($group, $this->global_groups))
			$prefix = '';
		else
			$prefix = $this->blog_id . ':';
		return preg_replace('/\s+/', '', "$prefix$group:$key");
	}
    
    function set($key, $data, $group = 'default', $ttl = 0){
        $key = $this->key($key,$group);
        if ( isset($this->tmp_cache[$key]) ) 
            return false;
        if ( in_array($group, $this->no_redis_groups) ) 
            return true;
        if ( $this->is_seriable($data) ) {
            $this->redis->set($key, $this->perform_serialization($data), $ttl);
        } else { 
            $this->redis->set($key, $data, $ttl);
        }
        $this->tmp_cache[$key] = $data;
        return true;
    }
    

    function get($key, $group='default', $expire = 0){
       $key = $this->key($key,$group);
       if ( isset($this->tmp_cache[$key]) ) {
            return $this->tmp_cache[$key]; 
       }
       $get = $this->redis->get($key);
       $tmp = unserialize($get);
       if ( $tmp )
          return $tmp;
       return $get;
    }
    
    function is_seriable($var){
        if ( is_array($var) )
            return true;
        else if ( is_object($var) )
            return true;
        else
            return false;
    }
    function perform_serialization($var){
        return serialize($var);
    }
    
    function _debug($msg){
        if ( $this->_debug )
            echo "'{$msg}'\n<br/>";
    }
    
    function _log($msg){
        $fp = @fopen('log.txt','a+');
        if ( ! $fp ) {
            //echo "could not write to log";
            return;
        } else { 
            fwrite($fp, $msg . "\n");
            fclose($fp);
        }
    }
    
}


class redis_wp_cache { 
    
    var $redis;
    
    function connect($server){
        try {
            $this->redis = @ new Predis_Client($server);
        } catch(Predis_CommunicationException $e){}
    }

    function close(){
        $this->redis->disconnect();
    }
    
    function get($key){
        $this->redis->get($key);
    }
    
    function set($key,$data, $ttl = 0 ){
        
        if ( $ttl == 0 )
            $this->redis->set($key,$data);
        else
            $this->redis->setex($key,$ttl, $data);
    
    }
    
    function flushdb(){
        $this->redis->flushdb();
    }
    
}