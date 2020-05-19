<?php

namespace Drupal\procesar_prensa\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Site\Settings;

/**
 *
 * @QueueWorker(
 * id = "prensa_loader",
 * title = "procesar prensa Queue Worker",
 * )
 */


class loaderEventBase extends QueueWorkerBase {
    
    public function processItem($data) {
        if ($data){

           $servicio = \Drupal::service('procesar_prensa.toolbox');
           
           if (!$servicio->loadMediaPrensa($data)){
                \Drupal::logger('procesar_prensa')->error(" Error Durante el proceso de traducir el contenido:".$data->id);
                
            };
        }else  
            \Drupal::logger('procesar_prensa')->error(" Error Durante el proceso de desencolar.");

    }
    
}


    