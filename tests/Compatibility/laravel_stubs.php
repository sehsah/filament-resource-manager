<?php
namespace Illuminate\Database\Eloquent { class Builder { public function __call($m,$a){ return $this; } } class Model { public function __call($m,$a){ return $this; } public static function __callStatic($m,$a){ return null; } protected static function boot() {} protected static function booted() {} protected static function booting() {} public static function saved($callback) {} public static function deleted($callback) {} } }
namespace Illuminate\Contracts\Support { interface Htmlable { public function toHtml(); } }
namespace Illuminate\Contracts\Cache { interface Repository {} }
namespace Illuminate\Support\Facades { class Cache { public static function store($s=null){ return null; } } class Schema { public static function hasTable($t){ return false; } } class Gate { public static function allows($a){ return true; } } }
namespace Illuminate\Support { class ServiceProvider { protected $app; public function __construct($app=null){ $this->app=$app; } public function register(): void {} public function boot(): void {} protected function mergeConfigFrom($p,$k){} protected function loadTranslationsFrom($p,$n){} protected function publishes(array $p,$g=null){} protected function commands($c){} } }
namespace Illuminate\Console { class Command { protected $signature; protected $description; public $components; public function option($k){ return null; } } }
namespace { 
  if (!function_exists('config')) { function config($k=null,$d=null){ return $d; } }
  if (!function_exists('filled')) { function filled($v){ return !empty($v); } }
  if (!function_exists('blank')) { function blank($v){ return empty($v); } }
  if (!function_exists('class_basename')) { function class_basename($c){ $c=is_object($c)?get_class($c):$c; return basename(str_replace('\\','/',$c)); } }
  if (!function_exists('__')) { function __($k,$r=[]){ return $k; } }
  if (!function_exists('e')) { function e($v,$d=true){ return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8', $d); } }
  if (!function_exists('app')) { function app($a=null){ return new stdClass; } }
  if (!function_exists('config_path')) { function config_path($p=''){ return $p; } }
  if (!function_exists('database_path')) { function database_path($p=''){ return $p; } }
  if (!function_exists('resource_path')) { function resource_path($p=''){ return $p; } }
}
