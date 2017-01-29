<?php
/** -------------------------------------------------------------------------------------------------------------------- ** 
/** -------------------------------------------------------------------------------------------------------------------- ** 
/** ---																																					--- **
/** --- 													------------------------------														--- **
/** ---																{ setup.php }																	--- **
/** --- 													------------------------------														--- **
/** ---																																					--- **
/** ---		AUTEUR 	: Nicolas DUPRE																											--- **
/** ---																																					--- **
/** ---		RELEASE	: 10.07.2016																												--- **
/** ---																																					--- **
/** ---		VERSION	: 1.0																															--- **
/** ---																																					--- **
/** ---																																					--- **
/** --- 														---------------------------														--- **
/** ---															{ C H A N G E L O G }															--- **
/** --- 														---------------------------														--- **
/** ---																																					--- **
/** ---																																					--- **
/** ---		VERSION 1.0 : 10.07.2016																											--- **
/** ---		------------------------																											--- **
/** ---			- Première release																												--- **
/** ---																																					--- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **

	Requirements :
	--------------

	Input Params :
	--------------
	
		String $include_path (Liste des dossiers à consulter séparé par deux points (:))
		, Array $setups (Liste des fichier à charger)
		[, String $file_pattern ({null} | modèle des fichiers à chargé où $1 est replacer par la valeur de $setup )]
	
	
	Output Params :
	---------------

	Objectif du script :
	---------------------
	
	Description fonctionnelle :
	----------------------------
	
		Un chemin inclus indiqué peut être vu de différente facon. Exemple
		
			Setup : Chemin relatif et vaut ./Setup
			
			/Setup : soit c'est absolute dans le web, soit absolute sur le serveur
				Pseudo-absolus sur le web si __DIR__/Setup
				Absolu si /Setup
			

/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **/
function setup($include_path, $setups, $file_pattern=null){
	/** 1. Récupération des dossier d'inclusion **/
	$include_path = explode(':', $include_path);
	
	/** 2. Charger les setups **/
	if(is_array($setups)){
		foreach($setups as $skey => $svalue){
			/** Modèle de recherche **/
			$search = ($file_pattern === null) ? "$svalue" : (preg_replace('#\$1#', $svalue, $file_pattern));
			
			/** Parcourir les includes_path **/
			foreach($include_path as $ikey => $ivalue){
				$full_path = null;
				$rel_path = null;
				
				/** Cas Absolu, par rapport au site web et non au serveur **/
				if(preg_match("#^/#", $ivalue)){
					$full_path = __ROOT__ . "$ivalue";
				} 
				
				/** Cas relatif, avec remonté d'arborescence **/
				else if (preg_match('#^(../)+#', $ivalue)){
					/** Récupérer le début de la chaine **/
					preg_match_all('#^(../)+#', $ivalue, $dbdotslashs);
					
					/** Compter le nombre de ../ présent en début de chaine **/
					$dbdotslashs_occ = substr_count($dbdotslashs[0][0], '../');
					
					$rel_path = __ROOT__ . $_SERVER['REQUEST_URI'];
					
					for($i = 0; $i <= $dbdotslashs_occ; $i++){
						$rel_path = substr($rel_path, 0, strrpos($rel_path, "/"));
					}
					
					$full_path = $rel_path."/".str_replace($dbdotslashs[0][0], "", $ivalue);
				}
				
				/** Autres cas **/
				else {
					$full_path = __ROOT__ . $_SERVER['REQUEST_URI'] . "/" . $ivalue;
				}
				
				/** Supprimer les éventuel ./ et ../ **/
				$full_path = str_replace("../", "", $full_path);
				$full_path = str_replace("./", "", $full_path);
				$full_path = str_replace("//", "/", $full_path);
				
				/** Analyse du dossier **/
				if(file_exists($full_path)){
					$folder = scandir($full_path);
				} else {
					$folder = Array();
				}
				
				/** Rechercher le fichier **/
				array_map(
					function($el) use ($search, $full_path){
						if(!preg_match("#^[.]{1,2}$#", $el)){
							if(preg_match("#$search#", $el)){
								include_once "$full_path/$el";
								
								foreach(get_defined_vars() as $key => $value){
									$GLOBALS[$key] = $value;
								}
							}
						}
					},
					$folder
				);
			}
		}
	}
}
?>