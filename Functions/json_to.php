<?php
/** -------------------------------------------------------------------------------------------------------------------- ** 
/** -------------------------------------------------------------------------------------------------------------------- ** 
/** ---																																					--- **
/** --- 											------------------------------------------------										--- **
/** ---																	{ json_to }																	--- **
/** --- 											------------------------------------------------										--- **
/** ---																																					--- **
/** ---		AUTEUR 	: Nicolas DUPRE																											--- **
/** ---																																					--- **
/** ---		RELEASE	: 08.08.2016																												--- **
/** ---																																					--- **
/** ---		VERSION	: 1.0																															--- **
/** ---																																					--- **
/** ---																																					--- **
/** --- 														---------------------------														--- **
/** ---															{ C H A N G E L O G }															--- **
/** --- 														---------------------------														--- **
/** ---																																					--- **
/** ---																																					--- **
/** ---		VERSION 1.0 : 08.08.2016																											--- **
/** ---		------------------------																											--- **
/** ---			- Première release																												--- **
/** ---																																					--- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **


	Requirements :
	--------------

	Input Params :
	--------------
	
		String $json_string
		[, String|Constant $output_string ({constant}|variable)]
		[, Boolean $recursive ({false}|true)]
		[, String $recursive_link ({_}|\s+)]
	
	
	Output Params :
	---------------
	
		Boolean True = successfull, False = Error
		

	Objectif du script :
	---------------------
	
	Description fonctionnelle :
	----------------------------


/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **/
function json_to($json_string=null, $output_type='constant', $recursive=true, $recursive_link='_'){
	/** 1. Contrôle des paramètres - Au minium, 1 paramètres requis **/
	if($json_string === null){
		trigger_error('A lest one parameter is required and that must be a string in JSON.', E_USER_WARNING);
		return false;
	}
	
	
	/** 1. Supprimer les commentaires du fichier **/
	$json_string = preg_replace('#/\*\*.+\*\*/#m', '', $json_string);
	
	
	/** 2. Controler la syntaxe JSON **/
	$json_array = json_decode($json_string, true);
	
	if(json_last_error() > 0){
		trigger_error('JSON String supplie is not valid : '.json_last_error_msg().' in : "<span style="color: red;">'.$json_string.'</span>"', E_USER_WARNING);
		return false;
	}
	
	
	/** 3. Parcourir le tableau de donnée obtenu **/
		// Déclaration de la fonction de lecture
		if(!function_exists('json_to_read_array')){
			function json_to_read_array($array, $output_type, $recursive, $recursive_link, $backtrace=null){
				/** Parcourir le tableau **/
				foreach($array as $akey => $value){
					$name = ($backtrace !== null) ? ($backtrace.$recursive_link.$akey) : ($akey);
					
					/** Si $value est un tableau et que recursive vaut vrai, alors recursion **/
					if(is_array($value) && $recursive){
						
						json_to_read_array($value, $output_type, $recursive, $recursive_link, $name);
					}
					/** Sinon, traiter la donnée tel qu'attendue **/
					else {
						switch(strtolower($output_type)){
							case 'variable':
								$GLOBALS[$name] = $value;
							break;
							
							case 'constant':
							default:
								define($name, $value);
							break;
						}
					}
				}
			}
		}
		
		// Lecture
		json_to_read_array($json_array, $output_type, $recursive, $recursive_link);
	
	
	/** 4. Traitement terminé avec succès, renvoyer VRAI **/
	return true;
}
?>