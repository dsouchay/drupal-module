<?php

namespace Drupal\revista_prensa\services;

Use \Drupal\node\Entity\Node;
use \Drupal\views\Views;
use \Drupal\file\Entity\File;

class RevistaToolbox{

	public function getRevistaPrensa(){
		$argumento=[];
		$paginado=[];
		$resultado=[];
		$activofecha = [];
		$request = \Drupal::request();
		$fechaoriginal=$request->getRequestUri();
		$fechaoriginal=str_replace("/revistaprensa/","", $fechaoriginal);
		$valido = $this->chequeaFechaValida($fechaoriginal);
		if (!$valido){
			$fechaoriginal = date('Y-m-d');
		}	
		$fechaotra = new \DateTime($fechaoriginal);
		$fechaotra->add(new \DateInterval('P1D'));
		$fechaotra=$fechaotra->format('Y-m-d');
		$fecha=date("d-m-Y",strtotime($fechaoriginal));
		$fechaotra=date("d-m-Y",strtotime($fechaotra));
		$noticias='';
		$thisView = Views::getView('publicaciones_servicios_portada');
		if (is_object($thisView)) {
			$thisView->setDisplay('block_1');
			$filters = $thisView->display_handler->getOption('filters');
			$filters['field_fecha_de_publicacion_value']['value']['value'] =$fecha;
			$filters['field_fecha_de_publicacion_value_1']['value']['value'] =$fechaotra;
			$thisView->display_handler->overrideOption('filters', $filters);
			$thisView->preExecute();
			$thisView->execute();
			$noticias = $thisView->buildRenderable('block_1');
		}
		$paginado = $this->getDiasAntes();
		//$paginado = $this->getDiasAntesSinFinesDeSemanaNiFeriados();
		for($i=0;$i<count($paginado);$i++){
			$activo='';
			if ($paginado[$i]==$fechaoriginal)$activo="activo";
			$activofecha[$paginado[$i]]=$activo;
		}
		$resultado['noticias']=$noticias;
		$resultado['paginado']=$paginado;
		$resultado['activo']=$activofecha;
		return $resultado;
	}
	
	public function getEnlaces(){
		$parameters = \Drupal::routeMatch()->getParameters();
		$node= $parameters->get('node');
		$enlaces = [];
		$nidvideo = count($node->field_video_contenido->getValue())>0?$node->field_video_contenido->getValue()[0]['target_id']:'';
		$niddocumento = count($node->field_documento_contenido->getValue())>0?$node->field_documento_contenido->getValue()[0]['target_id']:'';
		$nidaudio = count($node->field_audio_contenido->getValue())>0?$node->field_audio_contenido->getValue()[0]['target_id']:'';
				
		$enlaces['documento'] = '';
		$enlaces['documentourl']='';
		if ($niddocumento){
			$nodedocumento = Node::load($niddocumento);
			if (count($nodedocumento->field_documento->getValue())>0){
				$doc = File::load($nodedocumento->field_documento->getValue()[0]['target_id']);
				$doc = $doc->getFileUri();	
				$enlaces['documento'] = $doc;
			}
			$enlaces['documentourl'] = $nodedocumento->field_enlace_externo_de_sharepoi->getValue()?$nodedocumento->field_enlace_externo_de_sharepoi->getValue()[0]['uri']:'';
		}
		$enlaces['id']='';
		$enlaces['audio'] = '';
		$enlaces['audiourl']='';
		if ($nidaudio){
			$nodeaudio = Node::load($nidaudio);
			if ($nodeaudio->field_fichero_audio->getValue()){
				$doc = File::load($nodeaudio->field_fichero_audio->getValue()[0]['target_id']);
				$doc = $doc->getFileUri();
				$enlaces['audio'] = $doc;
				$enlaces['id'] = $node->id().$nodeaudio->id();				
			}
			$enlaces['audiourl'] = $nodeaudio->field_enlace_externo_de_sharepoi->getValue()?$nodeaudio->field_enlace_externo_de_sharepoi->getValue()[0]['uri']:'';
		}
		$enlaces['video'] = '';
		$enlaces['videourl']='';
		if ($nidvideo){
			$nodevideo = Node::load($nidvideo);
			$enlaces['video'] = $nodevideo->field_video->getValue()?$nodevideo->field_video->getValue()[0]['value']:'';
			$enlaces['videourl'] = $nodevideo->field_enlace_externo_de_sharepoi->getValue()?$nodevideo->field_enlace_externo_de_sharepoi->getValue()[0]['uri']:'';
		}
		$enlaces['urlmedio'] = $node->field_enlace->getValue()?$node->field_enlace->getValue()[0]['uri']:'';
		return $enlaces;
	}	
	
	private function diferenciaDias($inicio, $fin)
	{
		$inicio = strtotime($inicio);
		$fin = strtotime($fin);
		$dif = $fin - $inicio;
		$diasFalt = (( ( $dif / 60 ) / 60 ) / 24);
		return ceil($diasFalt);
	}

	private function chequeaFechaValida($fecha){
		$hoy = date('Y-m-d');
		$cantidaddias=$hoy>$fecha?$this->diferenciaDias($fecha,$hoy):$this->diferenciaDias($hoy,$fecha);
		if ($cantidaddias>14) return false;
		return $this->validateDate($fecha);
	}
	
	private function validateDate($date, $format = 'Y-m-d')
	{
	    $d = \DateTime::createFromFormat($format, $date);
	    return $d && $d->format($format) == $date && $d->format('N')!== '6'&& $d->format('N')!== '7';
	}
	
	private function getDiasAntes($cantidad=14)
	{
		$fechaspaginado = [];
		$fechaotra = new \DateTime('NOW');
		$fechaotra = $fechaotra->add(new \DateInterval('P1D'));
		for ($i=1;$i<=$cantidad;$i++){
			$fechaotra=$fechaotra->sub(new \DateInterval('P1D'));
			$fecha=$fechaotra->format('Y-m-d');
			$fechaspaginado[]=$fecha;			
		}
		$fechashabiles=[];
		foreach ($fechaspaginado as $day) {
			$day = new \DateTime($day);
			$i=0;
			// Asignamos un número por cada día de la semana 6 y 7 para sábado y domingo
			$weekDay = $day->format('N');
			// Si es sábado, domingo o festivo no lo imprime
			if ($weekDay !== '6' &&
					$weekDay !== '7') {
						$fechashabiles[]=$day->format('Y-m-d');
						$i++;
					}
					if ($i==$cantidad) break;
		}
		
		return $fechashabiles;
		
	}
	
	
	private function getDiasAntesSinFinesDeSemanaNiFeriados($cantidad=14)
	{
		$fechaspaginado = [];
		$holidays = [];
		$DiasFestivos = [];
		
		$format = 'Y-m-d';

		$startDateString = new \DateTime('NOW');
		$startDateString = $startDateString->format($format);
		
	
		
		$endDateTime = strtotime ( '-13 day' , strtotime ( $startDateString ) ) ;
		$endDateTime = date( 'Y-m-d' , $endDateTime );
		
		
		$dateInterval = new \DateInterval('P1D');
		
		$startDateTime = new \DateTime($startDateString);
		$endDateTime = new \DateTime($endDateTime);
		$dateInterval = new \DateInterval('P1D');
		
		$days = new \DatePeriod($endDateTime , $dateInterval, $startDateTime);
		$DiasFestivos[0] = '01-01'; // 1 de enero
		$DiasFestivos[1] = '01-06'; // 6 de enero
		$DiasFestivos[2] = '03-19'; // 19 de marzo
		$DiasFestivos[3] = '05-01'; // 1 de mayo
		$DiasFestivos[4] = '08-15'; // 15 de agosto
		$DiasFestivos[5] = '10-12'; // 12 de octubre
		$DiasFestivos[6] = '11-01'; // 1 de noviembre
		$DiasFestivos[7] = '12-06'; // 6 de diciembre
		$DiasFestivos[8] = '12-25'; // 25 de diciembre
		// festivos Regionales
		$DiasFestivos[9] = '05-02'; // 2 de mayo
		$DiasFestivos[10] = '05-15'; // 15 de mayo
		$DiasFestivos[11] = '09-09'; // 9 de noviembre
		// Semana Santa
		$anno = date('Y');
		$pascua=$this->calcularPascua($anno);
		
		$DiasFestivos = array_merge($DiasFestivos,$pascua);
		$DiasFestivos=[];
		foreach ($DiasFestivos as $dia){
			$holidays[ $anno.'-'.$dia] = true;
		}
		$i=0;
		foreach ($days as $day) {
			// Asignamos un número por cada día de la semana 6 y 7 para sábado y domingo
			$weekDay = $day->format('N');
			// Si es sábado, domingo o festivo no lo imprime
			if ($weekDay !== '6' &&
					$weekDay !== '7' &&
					!isset($holidays[$day->format('Y-m-d')])) {
						$fechaspaginado[]=$day->format('Y-m-d');
						$i++;
						
					//	echo PHP_EOL;
					}
			if ($i==$cantidad) break;
		}
		var_dump($fechaspaginado);exit;
		
	
		return array_reverse($fechaspaginado);

	
	}
	
	private function calcularPascua($year){
		$pascua= easter_date($year);
		$result=[];	
		$juevessanto= strtotime('-3 days',$pascua);
		$juevessanto= date("m-d",$juevessanto);;
		$result[1]= $juevessanto;
		$viernessanto=strtotime('-2 days',$pascua);
		$viernessanto=date("m-d",$viernessanto);
		$result[2]= $viernessanto;
		return $result;
	
	}
}
