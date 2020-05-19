<?php
namespace Drupal\procesar_prensa\Services;

use Drupal\Core\Database\Connection;
use Exception;
use ZipArchive;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;



class ProcesarPrensaToolbox
{

    protected $database;
    
   
    
    
   /* $ftp_server = "";
    $uname = ""; //Enter your ftp username here.
    $pwd = ""; //Enter your ftp password here.
    $directory = "" //Enter the dir of the files on the server here.*/
    
    public function __construct(Connection $database)
    {
        $this->database = $database;
    }
    

    public function copiaFicherosFTP($directory){
        try {
            $ftp_server = Settings::get('ftp_server');
            $uname =  Settings::get('uname');
            $pwd =  Settings::get('pwd'); 
            $connection =  ftp_connect( $ftp_server) or die("Error connecting to  $ftp_server");
            $login = ftp_login( $connection,  $uname,  $pwd);
            
            if (ftp_chdir($connection, $directory)) {
                echo "Changed directory to: " . ftp_pwd($connection) . "\n";
            } else {
                echo "Error while changing directory!\n";
            }
            ftp_pasv($connection,TRUE);
            $files = ftp_nlist($connection, ".");
            foreach ($files as $file){
                
                $file_parts = pathinfo($filename);
                if ($file_parts[extension]!='zip') continue;
                
                $newFile = fopen($file, 'w');
                ftp_nb_fget($connection, $newFile, $file, FTP_BINARY);
                fclose($newFile);
            }
            ftp_close($connection);
        }catch (\Exception $e2) {
            \Drupal::logger('procesar_prensa')->error($e2->getMessage() . " :: No se pudo establecer conexión con el directorio FTP");
        }
    }
    
    
    
    
    public function EliminarFicherosFTP($directory){
        try {
            $ftp_server = Settings::get('ftp_server');
            $uname =  Settings::get('uname');
            $pwd =  Settings::get('pwd');
            $connection =  ftp_connect( $ftp_server) or die("Error connecting to  $ftp_server");
            $login = ftp_login( $connection,  $uname,  $pwd);
            
            if (ftp_chdir($connection, $directory)) {
                echo "Changed directory to: " . ftp_pwd($connection) . "\n";
            } else {
                echo "Error while changing directory!\n";
            }
            ftp_pasv($connection,TRUE);
            $files = ftp_nlist($connection, ".");
            foreach ($files as $file){
                if (ftp_delete($connection, $file)) {
                    echo "$file se ha eliminado satisfactoriamente\n";
                } else {
                    echo "No se pudo eliminar $file\n";
                }
            }
            ftp_close($connection);
        }catch (\Exception $e2) {
            \Drupal::logger('procesar_prensa')->error($e2->getMessage() . " :: No se pudo establecer conexión con el directorio FTP");
        }
    }
    
    public function DirectorioVacio($path){
        return count( array_diff(scandir($path), array('.','..')))>0?false:true;
    }	
    
    public function DescomprimirZips($ruta){
        $directorio = opendir($ruta); //ruta actual
        while ($archivo = readdir($directorio)) //obtenemos un archivo y luego otro sucesivamente
        {
            if ($archivo != '.' and  $archivo != '..')//verificamos si es o no un directorio
            {
                $file_parts = pathinfo($archivo);
                if ($file_parts["extension"]!='zip')  continue;                
                    $zip = new ZipArchive;
                    $res = $zip->open($ruta.$archivo);
                    if ($res === TRUE) {
                        // extract it to the path we determined above
                        $zip->extractTo($ruta);
                        $zip->close();
                        unlink($ruta.'/'.$archivo);
                    } else {
                        \Drupal::logger('procesar_prensa')->error( " :: El fichero no es tiene extensión zip");
                    }
            }

        }
                     
    }
    
    public function procesaFicherosXML($ruta){
        $servicioloader = \Drupal::service('procesar_prensa.xml');
        $files = $servicioloader->obtenerInfoArchivos($ruta);
        return $files;       
    }
    
    
    public function getInformacion($ruta){
        $servicioloader = \Drupal::service('procesar_prensa.xml');
        $files = $servicioloader->leerXML($ruta);
        
    }
    
    
    public function ccCreateFile($path, $fileName, $date, $save_path, $espacio = 0)
    {
        $savePathZip = ($espacio) ? 'private://' . $save_path . $date . '/' : 'public://' . $save_path . $date . '/';
        $uri = $path . $fileName;
        $file = File::create([
            'uri' => $uri
        ]);
        $file->save();
        $fileNewUri = $savePathZip . $fileName;
        $file = $this->copiaFichero($file, $fileNewUri);
        return $file;
    }
    
    
    public function copiaFichero($file, $fileNewUri)
    {
        $fileUri = \Drupal::service('file_system')->realpath($file->getFileUri());
        $realFileNewUri = \Drupal::service('file_system')->realpath($fileNewUri);
        if (!$fileUri)   \Drupal::logger('procesar_prensa')->error("No se ha encontrado el fichero en el directorio indicado: ".$file->getFileUri());
        @copy($fileUri, $realFileNewUri);
        $file->setFileUri($fileNewUri);
        $file->setPermanent();
        $file->save();
        return $file;
    }
    
    
    public function loadMediaPrensa($data)
    {
        
        $arraydatos = json_decode(json_encode($data), True);
        $date = date('Ym');
        $save_path = "/prensa";
        $path = Settings::get('directory');
        $fileName=$data->pdf;
        $extension = @end(explode('.', $fileName));
        $contenidosmedias = [];
        $publicSavePath = 'public://' . $save_path . $date;
       // $privateSavePath = 'private://' . $save_path . $date;
        if (! is_dir($publicSavePath)) {
            $exito = @mkdir($publicSavePath, 0777, true);
        }

        
        // Creo el fichero principal del Tipo de contenido
        $file = $this->ccCreateFile($path, $fileName, $date, $save_path, '0');
        
        // Creo el las medias asociadas principal del Tipo de contenido
        $array = [
            'bundle' => 'prensa',
            'uid' => '0',
        ];
        
        $array['field_id_migracion']=$data->id;
        $array['field_referencia']=mb_convert_encoding($data->medio,"ISO-8859-1", "UTF-8");
        $array['field_fichero_original']=$data->pdf;
        $array['field_observaciones']=mb_convert_encoding ($data->descripcion, "ISO-8859-1", "UTF-8");
        
        $array['name']=mb_convert_encoding($data->title,"ISO-8859-1", "UTF-8");
        $array['field_fichero_original']=$data->pdf;
        $array['field_descripcion']=mb_convert_encoding ($data->medio,"ISO-8859-1", "UTF-8");
        $array['field_fecha']=$data->fecha;
        $array['field_resumen']=mb_convert_encoding ($data->descripcion,"ISO-8859-1", "UTF-8");
        $array['field_medio_procedencia']=mb_convert_encoding($data->medio,"ISO-8859-1", "UTF-8");
        $array['field_pagina']=$data->page;
        $array['field_taxonomias']=$data->page;
        $array['field_difusion_egm']=$data->egm;
        $array['field_difusion_ojd']=$data->ojd;
        $array['field_titulo']=mb_convert_encoding($data->title,"ISO-8859-1", "UTF-8");
        
        
      /*  foreach ($arraydatos as $key => $value) {
            $array['field_' . $key] = $value[0];
        }*/
        $media = Media::create($array);
        $media->save();
        $contenidosmedias = [$media];//$servicioloader->ccCreateMedia($media_metadata, $TC, $date, $contentType);
        foreach ($contenidosmedias as $item) {
            $item->field_media_file = $file;
            $item->save();
        }
        
        // Se procesa el fichero principal devolviendo el fichero del tumbnail.(Ej.: Zip se descomprime el zip y se extrae el thumbnail que se encuentra dentro y se devuelve)
       // $filepic = $servicioloader->ccProcesaFicheroTipoContenido($file, $save_path, $date, $extension);
        $this->borrarFicherosMismoNombre($path,$data->pdf);
        $this->borrarFicherosMismoNombre($path,$data->ocr);
        $this->borrarFicherosMismoNombre($path,$data->xml);
        
        return true;
    }
    

    
    public function borrarFicherosDirectorio($dir){
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            
            if (!is_dir($dir.$file))  unlink($dir.$file);
        }
    }
    
    public function borrarFicherosMismoNombre($dir,$file){
           
            if (!is_dir($dir.$file))  unlink($dir.$file);
        
    }
    
    
}
