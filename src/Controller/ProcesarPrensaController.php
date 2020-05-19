<?php


namespace Drupal\procesar_prensa\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\procesar_prensa\Services\procesarPrensaToolbox;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


class ProcesarPrensaController extends ControllerBase  {
    
  
    
    
    public function content() {
        $ruta = Settings::get('directory');
        $argumento =  \Drupal::service('procesar_prensa.toolbox');
        
        //  $this->argumento->copiaFicherosFTP($ruta);
        //  $this->argumento->EliminarFicherosFTP($ruta);
        $argumento->DescomprimirZips($ruta);
        $nids =  $argumento->procesaFicherosXML($ruta);
        
        $build['#cache']['max-age'] = 0;
        $content = "sin procesar";
        
        if (count($nids)){
            $batch = array(
                'title' => t('Procesado de reportes de prensa de KantarMedia...'),
                'operations' => [],
                'init_message'     => t('Commencing'),
                'progress_message' => t('Processed @current out of @total.'),
                'error_message'    => t('An error occurred during processing'),
                'finished' => '\Drupal\procesar_prensa\Controller\ProcesarPrensaController::batch_prensa_finished',
            );
            foreach ($nids as $nid) {
                $operations[] = [
                    '\Drupal\procesar_prensa\Controller\ProcesarPrensaController::batch',
                    [
                        $nid
                    ],
                ];
            }
            $batch['operations'] = $operations;
            batch_set($batch);
            return batch_process(\Drupal::url('procesar_prensa.intermedio'));
            
        }else {
            $messenger = \Drupal::messenger();
            $messenger->addMessage(t('No se encontraron referencias de prensa a registrar.'));
            return new RedirectResponse(\Drupal::url('procesar_prensa.intermedio', [], ['absolute' => TRUE]));
        }
        
        return RedirectResponse(\Drupal::url('procesar_prensa.intermedio'));
    }
    
    
    
    
    public static function  batch_prensa_finished($success, $results, $operations) {
        $messenger = \Drupal::messenger();
        if ($success) {
            $messenger->addMessage(t('Preparación del procesado de automático de las referencias de presna ha finalizado.'));
        }
        else {
            $error_operation = reset($operations);
            $messenger->addMessage(
                t('An error occurred while processing @operation with arguments : @args',
                    [
                        '@operation' => $error_operation[0],
                        '@args' => print_r($error_operation[0], TRUE),
                    ]
                    )
                );
        }
        return new RedirectResponse(\Drupal::url('procesar_prensa.intermedio'));
    }
    
    public  static function  batch($file){
        $argumento =  \Drupal::service('procesar_prensa.toolbox');
        $argumento->getInformacion($file);
    }
    
    
    public function textofinal(){
        
       
        $content['end'] = [
            '#type' => 'markup',
            '#markup' => t('Algo se Hizo.') ,
            '#prefix' => '<div class="download-content-body"><p><h3>',
            '#sufix' =>'</h3>/p></div>'
        ];
        
        return $content;
    }
}