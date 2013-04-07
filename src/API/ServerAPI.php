<?php

/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


*/

class ServerAPI{
	var $restart = false;
	private static $serverRequest = false;
	private $server;
	private $config;
	private $apiList = array();
	
	public static function request(){
		return self::$serverRequest;
	}
	
	public function run(){
		$this->load();
		return $this->init();
	}
	
	public function load(){
		@mkdir(DATA_PATH."logs/", 0755, true);
		@mkdir(DATA_PATH."players/", 0755);
		@mkdir(DATA_PATH."worlds/", 0755);
		@mkdir(DATA_PATH."plugins/", 0755);
		console("[INFO] Starting ServerAPI server handler...");
		file_put_contents(DATA_PATH."logs/packets.log", "");
		if(!file_exists(DATA_PATH."logs/test.bin.log") or md5_file(DATA_PATH."logs/test.bin.log") !== TEST_MD5){
			console("[NOTICE] Executing tests...");
			console("[INFO] OS: ".PHP_OS.", ".Utils::getOS());
			console("[INFO] uname -a: ".php_uname("a"));
			console("[INFO] PHP Version: ".phpversion());
			console("[INFO] Endianness: ".ENDIANNESS);
			$test = b"";
			$test .= Utils::writeLong("5567381823242127440");
			$test .= Utils::writeLong("2338608908624488819");
			$test .= Utils::writeLong("2333181766244987936");
			$test .= Utils::writeLong("2334669371112169504");
			$test .= Utils::writeShort(Utils::readShort("\xff\xff\xff\xff"));
			$test .= Utils::writeShort(Utils::readShort("\xef\xff\xff\xff"));
			$test .= Utils::writeInt(Utils::readInt("\xff\xff\xff\xff"));
			$test .= Utils::writeInt(1);
			$test .= Utils::writeInt(-1);
			$test .= Utils::writeFloat(Utils::readFloat("\xff\xff\xff\xff"));
			$test .= Utils::writeFloat(-1.584563155838E+29);
			$test .= Utils::writeFloat(1);
			$test .= Utils::writeLDouble(Utils::readLDouble("\xff\xff\xff\xff\xff\xff\xff\xff"));
			$test .= Utils::writeLong("-1152921504606846977");
			$test .= Utils::writeLong("-1152921504606846976");
			$str = new Java_String("TESTING\x00\n\r\t\xff");
			$test .= Utils::writeInt($str->hashCode());
			$test .= Utils::writeDataArray(array("a", "b", "c", "\xff\xff\xff\xff"));
			$test .= Utils::hexToStr("012334567890");
			file_put_contents(DATA_PATH."logs/test.bin.log", $test);
			$md5 = md5($test);
			console("[INFO] MD5 of test: ".$md5);
			if($md5 !== TEST_MD5){
				console("[ERROR] Test error, please send your console.log + test.bin.log to the Github repo");
				die();
			}
		}

		console("[DEBUG] Loading server.properties...", true, true, 2);
		$this->config = new Config(DATA_PATH . "server.properties", CONFIG_PROPERTIES, array(
			"server-name" => "Minecraft Server",
			"description" => "Server made using PocketMine-MP",
			"motd" => "Welcome @username to this server!",
			"invisible" => false,
			"server-ip" => "0.0.0.0",
			"port" => 19132,
			"memory-limit" => "256M",
			"last-update" => false,
			"white-list" => false,
			"debug" => 1,
			"max-players" => 20,
			"server-type" => "normal",
			"time-per-second" => 20,
			"gamemode" => 1,
			"difficulty" => 1,
			"generator" => "",
			"generator-settings" => "",
			"level-name" => false,
			"server-id" => false,
			"upnp-forwarding" => false,
			"send-usage" => true,
		));
		$this->parseProperties();
		define("DEBUG", $this->getProperty("debug"));
		$this->server = new PocketMinecraftServer($this->getProperty("server-name"), $this->getProperty("gamemode"), false, $this->getProperty("port"), $this->getProperty("server-id"), $this->getProperty("server-ip"));
		self::$serverRequest = $this->server;
		$this->setProperty("server-id", $this->server->serverID);
		$this->server->api = $this;
		$gitsha1 = false;
		if(file_exists(FILE_PATH.".git/refs/heads/master")){ //Found Git information!
			$gitsha1 = trim(file_get_contents(FILE_PATH.".git/refs/heads/master"));
			console("[GIT] Commit \x1b[33m".$gitsha1);
		}
		
		if($this->getProperty("upnp-forwarding") === true){
			console("[INFO] [UPnP] Trying to port forward...");
			UPnP_PortForward($this->getProperty("port"));
		}
		if(($ip = Utils::getIP()) !== false){
			console("[INFO] External IP: ".$ip);
		}
		if($this->getProperty("last-update") === false or ($this->getProperty("last-update") + 3600) < time()){
			console("[INFO] Checking for new server version");
			console("[INFO] Last check: \x1b[36m".date("Y-m-d H:i:s", $this->getProperty("last-update"))."\x1b[0m");
			$info = json_decode(Utils::curl_get("http://www.pocketmine.net/latest"), true);
			if($this->server->version->isDev()){
				if($info === false or !isset($info["development"])){
					console("[ERROR] PocketMine API error");
				}else{
					$last = $info["development"]["date"];
					if($last >= $this->getProperty("last-update") and $this->getProperty("last-update") !== false and $gitsha1 != $info["development"]["commit"]){
						console("[NOTICE] \x1b[33mA new DEVELOPMENT version of PocketMine-MP has been released");
						console("[NOTICE] \x1b[33mVersion \"".$info["development"]["version"]."\" [".substr($info["development"]["commit"], 0, 10)."]");
						console("[NOTICE] \x1b[36mIf you want to update, get the latest version at ".$info["development"]["download"]);
						console("[NOTICE] This message will dissapear after issuing the command \"/update-done\"");
						sleep(3);
					}else{
						$this->setProperty("last-update", time());
						console("[INFO] \x1b[36mThis is the latest DEVELOPMENT version");
					}
				}
			}else{
				if($info === false or !isset($info["stable"])){
					console("[ERROR] PocketMine API error");
				}else{
					$newest = new VersionString(MAJOR_VERSION);
					$newestN = $newest->getNumber();
					$update = new VersionString($info["stable"]["version"]);
					$updateN = $update->getNumber();
					if($updateN > $newestN){
						console("[NOTICE] \x1b[33mA new STABLE version of PocketMine-MP has been released");
						console("[NOTICE] \x1b[36mVersion \"".$info["stable"]["version"]."\" #".$updateN);
						console("[NOTICE] Download it at ".$info["stable"]["download"]);
						console("[NOTICE] This message will dissapear as soon as you update");
						sleep(5);
					}else{
						$this->setProperty("last-update", time());
						console("[INFO] \x1b[36mThis is the latest STABLE version");
					}
				}

			}
		}
		if(file_exists(DATA_PATH."worlds/level.dat")){
			console("[NOTICE] Detected unimported map data. Importing...");
			$this->importMap(DATA_PATH."worlds/", true);
		}
		$this->server->mapName = $this->getProperty("level-name");
		$this->server->mapDir = DATA_PATH."worlds/".$this->server->mapName."/";
		if($this->server->mapName === false or trim($this->server->mapName) === "" or (!file_exists($this->server->mapDir."chunks.dat") and !file_exists($this->server->mapDir."chunks.dat.gz"))){
			if($this->server->mapName === false or trim($this->server->mapName) === ""){
				$this->server->mapName = "world";
			}
			$this->server->mapDir = DATA_PATH."worlds/".$this->server->mapName."/";
			$generator = "SuperflatGenerator";
			if($this->getProperty("generator") !== false and class_exists($this->getProperty("generator"))){
				$generator = $this->getProperty("generator");
			}
			$this->gen = new WorldGenerator($generator, $this->server->seed);
			if($this->getProperty("generator-settings") !== false and trim($this->getProperty("generator-settings")) != ""){
				$this->gen->set("preset", $this->getProperty("generator-settings"));
			}
			$this->gen->init();
			$this->gen->generate();
			$this->gen->save($this->server->mapDir, $this->server->mapName);
			$this->setProperty("level-name", $this->server->mapName);
			$this->setProperty("gamemode", 1);
		}
		$this->loadProperties();
		$this->server->loadMap();
		
		console("[INFO] Loading default APIs");
		
		$this->loadAPI("console", "ConsoleAPI");
		$this->loadAPI("level", "LevelAPI");
		$this->loadAPI("block", "BlockAPI");
		$this->loadAPI("chat", "ChatAPI");
		$this->loadAPI("ban", "BanAPI");		
		$this->loadAPI("entity", "EntityAPI");		
		$this->loadAPI("tileentity", "TileEntityAPI");
		$this->loadAPI("player", "PlayerAPI");
		$this->loadAPI("time", "TimeAPI");
		
		foreach($this->apiList as $ob){
			if(is_callable(array($ob, "init"))){
				$ob->init(); //Fails sometimes!!!
			}
		}
		$this->loadAPI("plugin", "PluginAPI"); //fix :(
		$this->plugin->init();
		
		
		$this->server->loadEntities();
	}
	
	public function sendUsage(){
		console("[INTERNAL] Sending usage data...", true, true, 3);
		$plist = "";
		foreach($this->plugin->getList() as $p){
			$plist .= str_replace(array(";", ":"), "", $p["name"]).":".str_replace(array(";", ":"), "", $p["version"]).";";
		}
		Async::call("Utils::curl_post", array(
			0 => "http://stats.pocketmine.net/usage.php",
			1 => array(
				"serverid" => $this->server->serverID,
				"os" => Utils::getOS(),
				"version" => MAJOR_VERSION,
				"protocol" => CURRENT_PROTOCOL,
				"online" => count($this->server->clients),
				"max" => $this->server->maxClients,
				"plugins" => $plist,
			),
			2 => 10
		));
	}

	public function __destruct(){
		foreach($this->apiList as $ob){
			if(is_callable($ob, "__destruct")){
				$ob->__destruct();
				unset($this->apiList[$ob]);
			}
		}
	}


	private function loadProperties(){
		if(($memory = str_replace("B", "", strtoupper($this->getProperty("memory-limit")))) !== false){
			$value = array("M" => 1, "G" => 1024);
			$real = ((int) substr($memory, 0, -1)) * $value[substr($memory, -1)];
			if($real < 128){
				console("[ERROR] PocketMine doesn't work right with less than 128MB of RAM", true, true, 0);
			}
			@ini_set("memory_limit", $memory);
		}else{
			$this->setProperty("memory-limit", "256M");
		}
		if(!$this->config->exists("invisible")){
			$this->config->set("invisible", false);
		}
		if($this->server instanceof PocketMinecraftServer){
			$this->server->setType($this->getProperty("server-type"));
			$this->server->timePerSecond = $this->getProperty("time-per-second");
			$this->server->invisible = $this->getProperty("invisible");
			$this->server->maxClients = $this->getProperty("max-players");
			$this->server->description = $this->getProperty("description");
			$this->server->motd = $this->getProperty("motd");
			$this->server->gamemode = $this->getProperty("gamemode");
			$this->server->difficulty = $this->getProperty("difficulty");
			$this->server->whitelist = $this->getProperty("white-list");
			$this->server->reloadConfig();
		}
	}

	private function writeProperties(){
		$this->config->save();
	}

	private function parseProperties(){
		foreach($this->config->getAll() as $n => $v){
			switch($n){
				case "last-update":
					if($v === false){
						$v = time();
					}else{
						$v = (int) $v;
					}
					break;
				case "gamemode":
				case "max-players":
				case "port":
				case "debug":
				case "difficulty":
				case "time-per-second":
					$v = (int) $v;
					break;
				case "server-id":
					if($v !== false){
						$v = preg_match("/[^0-9\-]/", $v) > 0 ? Utils::readInt(substr(md5($v, true), 0, 4)):$v;
					}
					break;
			}
			$this->config->set($n, $v);
		}
	}

	public function init(){
		if($this->getProperty("send-usage") !== false){
			$this->server->schedule(36000, array($this, "sendUsage"), array(), true); //Send usage data every 30 minutes
			$this->sendUsage();
		}
		$this->server->init();
		unregister_tick_function(array($this->server, "tick"));
		$this->__destruct();
		if($this->getProperty("upnp-forwarding") === true ){
			console("[INFO] [UPnP] Removing port forward...");
			UPnP_RemovePortForward($this->getProperty("port"));
		}
		return $this->restart;
	}

	/*-------------------------------------------------------------*/

	public function addHandler($e, $c, $p = 5){
		return $this->server->addHandler($e, $c, $p);
	}

	public function dhandle($e, $d){
		return $this->server->handle($e, $d);
	}

	public function handle($e, &$d){
		return $this->server->handle($e, $d);
	}

	public function action($t, $c, $r = true){
		return $this->server->action($t, $c, $r);
	}

	public function schedule($t, $c, $d, $r = false, $e = "server.schedule"){
		return $this->server->schedule($t, $c, $d, $r, $e);
	}

	public function event($e, $d){
		return $this->server->event($e, $d);
	}

	public function trigger($e, $d){
		return $this->server->trigger($e, $d);
	}

	public function deleteEvent($id){
		return $this->server->deleteEvent($id);
	}

	public function importMap($dir, $remove = false){
		if(file_exists($dir."level.dat")){
			$nbt = new NBT();
			$level = parseNBTData($nbt->loadFile($dir."level.dat"));
			if($level["LevelName"] == ""){
				$level["LevelName"] = "world".time();
			}
			console("[DEBUG] Importing map \"".$level["LevelName"]."\" gamemode ".$level["GameType"]." with seed ".$level["RandomSeed"], true, true, 2);
			unset($level["Player"]);
			$lvName = $level["LevelName"]."/";
			@mkdir(DATA_PATH."worlds/".$lvName, 0755);
			file_put_contents(DATA_PATH."worlds/".$lvName."level.dat", serialize($level));
			$entities = parseNBTData($nbt->loadFile($dir."entities.dat"));
			file_put_contents(DATA_PATH."worlds/".$lvName."entities.dat", serialize($entities["Entities"]));
			if(!isset($entities["TileEntities"])){
				$entities["TileEntities"] = array();
			}
			file_put_contents(DATA_PATH."worlds/".$lvName."tileEntities.dat", serialize($entities["TileEntities"]));
			console("[DEBUG] Imported ".count($entities["Entities"])." Entities and ".count($entities["TileEntities"])." TileEntities", true, true, 2);

			if($remove === true){
				rename($dir."chunks.dat", DATA_PATH."worlds/".$lvName."chunks.dat");
				unlink($dir."level.dat");
				@unlink($dir."level.dat_old");
				@unlink($dir."player.dat");
				unlink($dir."entities.dat");
			}else{
				copy($dir."chunks.dat", DATA_PATH."worlds/".$lvName."chunks.dat");
			}
			if($this->getProperty("level-name") === false){
				console("[INFO] Setting default level to \"".$level["LevelName"]."\"");
				$this->setProperty("level-name", $level["LevelName"]);
				$this->setProperty("gamemode", $level["GameType"]);
				$this->server->seed = $level["RandomSeed"];
				$this->server->spawn = array("x" => $level["SpawnX"], "y" => $level["SpawnY"], "z" => $level["SpawnZ"]);
				$this->writeProperties();
			}
			console("[INFO] Map \"".$level["LevelName"]."\" importing done!");
			unset($level, $entities, $nbt);
			return true;
		}
		return false;
	}
	
	public function getProperties(){
		return $this->config->getAll();
	}

	public function getProperty($name){
		if(($v = arg($name)) !== false){ //Allow for command-line arguments
			switch(strtolower(trim($v))){
				case "on":
				case "true":
				case "yes":
					$v = true;
					break;
				case "off":
				case "false":
				case "no":
					$v = false;
					break;
			}
			switch($name){
				case "last-update":
					if($v === false){
						$v = time();
					}else{
						$v = (int) $v;
					}
					break;
				case "gamemode":
				case "max-players":
				case "port":
				case "debug":
				case "difficulty":
				case "time-per-second":
					$v = (int) $v;
					break;
				case "server-id":
					if($v !== false){
						$v = preg_match("/[^0-9\-]/", $v) > 0 ? Utils::readInt(substr(md5($v, true), 0, 4)):$v;
					}
					break;
			}
			return $v;
		}
		return $this->config->get($name);
	}

	public function setProperty($name, $value){
		$this->config->set($name, $value);
		$this->writeProperties();
		$this->loadProperties();
	}

	public function getList(){
		return $this->apiList;
	}

	public function loadAPI($name, $class, $dir = false){
		if(isset($this->$name)){
			return false;
		}elseif(!class_exists($class)){
			$internal = false;
			if($dir === false){
				$internal = true;
				$dir = FILE_PATH."src/API/";
			}
			$file = $dir.$class.".php";
			if(!file_exists($file)){
				console("[ERROR] API ".$name." [".$class."] in ".$dir." doesn't exist", true, true, 0);
				return false;
			}
			require_once($file);
		}else{
			$internal = true;
		}
		$this->$name = new $class();
		$this->apiList[] = $this->$name;
		console("[".($internal === true ? "DEBUG":"INFO")."] API \x1b[36m".$name."\x1b[0m [\x1b[30;1m".$class."\x1b[0m] loaded", true, true, ($internal === true ? 2:1));
	}

}