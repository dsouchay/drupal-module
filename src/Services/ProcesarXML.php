<?php

namespace Drupal\procesar_prensa\services;

use Drupal\node\Entity\Node;
use Drupal\Core\Site\Settings;
use \Drupal\file\Entity\File;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\taxonomy\Entity\Term;

class ProcesarXML{
	
  public function DirectorioSinDirectorios(){
        $dir = Settings::get('directory');
  	$files = array_diff(scandir($dir), array('.','..'));
  	foreach ($files as $file) {
  		if (is_dir($dir.$file)) return true;
  	}

  
  	return false;
  }
  
  public function chequear(){
  	
  	if(null ===(Settings::get('revista_prensa_dir_leer') || !is_dir(Settings::get('revista_prensa_dir_leer')))){
  		\Drupal::logger('referencia_prensa')->error("No existe la ruta @path o no es un directorio", array("@path" => Settings::get('revista_prensa_dir_leer')));
  	}
  	if(null ===(Settings::get('revista_prensa_dir_pdf')) || !is_dir(Settings::get('revista_prensa_dir_pdf'))){
  		\Drupal::logger('referencia_prensa')->error("No existe la ruta @path o no es un directorio", array("@path" => Settings::get('revista_prensa_dir_leer')));
  	}
  	if(null ===(Settings::get('revista_prensa_dir_satisfactorio'))){
  		\Drupal::logger('referencia_prensa')->error("No existe la ruta @path o no es un directorio", array("@path" => 'revista_prensa_dir_satisfactorio'));
  	}
  	if(null ===(Settings::get('revista_prensa_dir_problema'))){
  		\Drupal::logger('referencia_prensa')->error("No existe la ruta @path o no es un directorio", array("@path" => 'revista_prensa_dir_problema'));
  	}
  	if(null ===(Settings::get('revista_prensa_dir_problema'))){
  		\Drupal::logger('referencia_prensa')->error("No existe la ruta @path o no es un directorio", array("@path" => 'revista_prensa_selector'));
  	}

  	return (null !==(Settings::get('revista_prensa_dir_leer')) and is_dir(Settings::get('revista_prensa_dir_leer')) 
  		and null !==(Settings::get('revista_prensa_dir_pdf')) and is_dir(Settings::get('revista_prensa_dir_pdf')) 
  			and null !==(Settings::get('revista_prensa_dir_satisfactorio')) and null !==(Settings::get('revista_prensa_dir_problema')) and null !==(Settings::get('revista_prensa_selector')) );
  }
  
  
  public function leerXML($path){
	if (file_exists($path)) {
		  $xml = @simplexml_load_file($path);
	    if (!$xml){
	    	\Drupal::logger('procesar_prensa')->error("No se puedo cargar el xml @path", array("@path" => $path));
			return false;
		}
		foreach ( $xml->NEWS as $new )  {
		    $filename = pathinfo($path)['basename'];
		    if ($filename=='index.xml') continue; 
			$queue_factory = \Drupal::service('queue');
			$queue = $queue_factory->get('prensa_loader');
			$loader = new \stdClass();
			$loader->id =  (string)$new->CODE;
			$loader->title = mb_convert_encoding((string)$new->TITLE, "ISO-8859-1", "UTF-8");
			$loader->medio = mb_convert_encoding((string)$new->MEDIUM, "ISO-8859-1", "UTF-8");
			$loader->fecha = (string) $new->DATE;
			$loader->pdf = (string) $new->FILE;
			$loader->txt = (string) $new->OCR;
			$loader->xml = (string) pathinfo($path)['basename'];
			$loader->page = (string) $new->PAGE;
			$loader->egm = (string) $new->EGM;
			$loader->ojd = (string) $new->OJD;
			$txt = (string)$new->OCR;
			$dir = Settings::get('directory');
			$loader->descripcion = mb_convert_encoding($this->leerFicheroTexto($dir.$txt), "ISO-8859-1", "UTF-8");
			$queue->createItem($loader);

		 }
		  return true;
		}
		\Drupal::logger('procesar_prensa')->error("No existe el fichero @path", array("@path" => $path));
		return false;
     }
     
     
     public function leerFicheroTexto($ArchivoLeer){
         $detalle = "";
         if(@touch($ArchivoLeer)){

             if(($archivoID = fopen($ArchivoLeer, "r"))) {
                 while( !@feof($archivoID)){
                     $linea = @fgets($archivoID, 1024);
                     
                     
                     
                     $detalle.= " ".$linea;
                 }
             }
             @fclose($archivoID);
         }
         $detalle =  mb_convert_encoding($detalle, 'UTF-8',
             mb_detect_encoding($detalle, 'UTF-8, ISO-8859-1', true));
         
         return $detalle;
     }
     
     
    public function obtenerInfoArchivos($ruta){
         $files = array(); 
         foreach (glob($ruta."/*.xml") as $file) { 
             $files[] = $file; 
         }
         return $files;
     }
	
	public function borrarFicherosDirectorio($dir){
			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file) {
				if (!is_dir($dir.$file))  unlink($dir.$file);
			}
			
	}

	
}
