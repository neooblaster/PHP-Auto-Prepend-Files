<?php
/** -------------------------------------------------------------------------------------------------------------------- ** 
/** -------------------------------------------------------------------------------------------------------------------- ** 
/** ---																																					--- **
/** --- 											-----------------------------------------------											--- **
/** ---														 { auto_prepend_file.php }														 	--- **
/** --- 											-----------------------------------------------											--- **
/** ---																																					--- **
/** ---		AUTEUR 	: Nicolas DUPRE																											--- **
/** ---																																					--- **
/** ---		RELEASE	: 27.01.2017																												--- **
/** ---																																					--- **
/** ---		VERSION	: 2.0																															--- **
/** ---																																					--- **
/** ---																																					--- **
/** --- 														-----------------------------														--- **
/** --- 															{ C H A N G E L O G } 															--- **
/** --- 														-----------------------------														--- **
/** ---																																					--- **
/** ---																																					--- **
/** ---		VERSION 2.0 : 27.01.2017																											--- **
/** ---		------------------------																											--- **
/** ---			- Amélioration du script																										--- **
/** ---				> Charge automatiquement les fichiers non exclus																	--- **
/** ---				> Lecture recursive des dossiers																							--- **
/** ---																																					--- **
/** ---			- Ajout d'un system d'exclusion de fichier et dossier																	--- **
/** ---				> Utilisation de la superglobale $_SERVER pour effectuer ses controle										--- **
/** ---				> permet l'utilisation d'expression réguliere dans les conditions et valeur d'exclusion				--- **
/** ---																																					--- **
/** ---																																					--- **
/** ---		VERSION 1.0 : 09.07.2016																											--- **
/** ---		------------------------																											--- **
/** ---			- Première release																												--- **
/** ---																																					--- **
/** ---																																					--- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **

	Objectif du script :
	---------------------
	
		Par le biais de se script auto_prepend depuis la configuration php.ini, celui-ci permet d'en charger autant de fichier 
	que nécessaire	qui sont déposé dans le dossier courant, sans qu'il soit nécessaire d'ajouter manuellement les fichiers dans php.ini
	
	Description fonctionnelle :
	----------------------------
	
	
		1. Création d'une variable unique $_PREPEND pour effacer toutes traces du traitement pour avoir une GLOBALS clean pour les DEVs.
			> Notation des variables plus lourde à lire
		
		2. Traitement
			2.1. S'ajouter en tant que fichier exclus
			2.2. Si un fichier .ignore existe, on le lit
			2.3. On parcour tous les dossiers/fichiers ou se trouve le présent script
			2.4. Efface toutes les traces (variables & fichier temporaire)
			
		3. Pour passer en mode debug avec affichage, remplacer les //--/ / par /*--* / (sans l'espace)
	
/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** ---																																					--- **
/** ---													PHASE 1 - INITIALISATION DU SCRIPT													--- **
/** ---																																					--- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **/
//--//echo"<pre>";
//--//error_reporting(E_ALL);


/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** ---																																					--- **
/** ---												PHASE 2 - INITIALISAITON DES DONNEES													--- **
/** ---																																					--- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **/
/** > Déclaration des variables **/
	$_PREPEND;	// ARRAY	:: Environnement de travail du script afin de laisser pur la global $GLOBALS


/** > Initialisation des variables **/
	$_PREPEND = Array(
		// ARRAY :: Liste des dossiers à traiter
		"folders" => Array(),
		
		// ARRAY :: Règles d'exclusions
		"ignore" => Array(
			// ARRAY :: Liste de dossier à exclure
			"folders" => Array(),
			"folders_regexp" => Array(),
			// ARRAY :: Liste des fichier à exclure
			"files" => Array(),
			"files_regexp" => Array()
		),
		
		// ARRAY :: Variables locales
		"local" => Array(),
	);




/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** ---																																					--- **
/** ---									PHASE 4 - EXECUTION DU SCRIPT DE TRAITEMENT DE DONNEES										--- **
/** ---																																					--- **
/** -------------------------------------------------------------------------------------------------------------------- **
/** -------------------------------------------------------------------------------------------------------------------- **/
/** --- S'auto-exclure des fichiers --- **/
/** ----------------------------------- **/
// > Permet tout renommage sans changement de fonctionnement
// > Empêche de créer une boucle infinie **/
$_PREPEND["ignore"]["files"][] = basename(__FILE__);



/** ---------------------------------------------- **/
/** --- Lecture du fichier .ignore s'il existe --- **/
/** ---------------------------------------------- **/
if(file_exists(__DIR__."/.ignore")){
	if(!file_exists(__DIR__."/.tmp")){
		mkdir(__DIR__."/.tmp", 0775);
	}
	
	$_PREPEND["local"]["skip_cleansing"] = false;
	$_PREPEND["local"]["ignore"] = fopen(__DIR__."/.ignore", "r");
	$_PREPEND["local"]["tmp_file_name"] = md5(file_get_contents(__DIR__."/.ignore"));
	
	/** Inutile de retraiter un fichier qui ne change que rarement **/
	/** Evite les conflits d'écriture en cas de traitement simutané **/
	/** Permet la sauvegarde des ancienne configuration **/
	if(file_exists(__DIR__."/.tmp/".$_PREPEND["local"]["tmp_file_name"])){
		$_PREPEND["local"]["skip_cleansing"] = true;
	}
	
	if(!$_PREPEND["local"]["skip_cleansing"]){
		$_PREPEND["local"]["ignore_tmp"] = fopen(__DIR__."/.tmp/".$_PREPEND["local"]["tmp_file_name"], "w+");
		
		/** Rechercher le BREAK_SIGNAL **/
		while($_PREPEND["local"]["buffer"] = fgets($_PREPEND["local"]["ignore"])){
			if(!preg_match("#\#\s*BREAK_SIGNAL#", $_PREPEND["local"]["buffer"])){
				fputs($_PREPEND["local"]["ignore_tmp"], $_PREPEND["local"]["buffer"]);
			}
			else {
				fclose($_PREPEND["local"]["ignore_tmp"]);
				break;
			}
		}
	
		/** Supprimer les commentaires **/
		$_PREPEND["local"]["ignore"] = file_get_contents(__DIR__."/.tmp/".$_PREPEND["local"]["tmp_file_name"]);
		$_PREPEND["local"]["ignore"] = preg_replace("@^\s*(#|;).*@mi", "", $_PREPEND["local"]["ignore"]);
		$_PREPEND["local"]["ignore"] = preg_replace("@//.*@mi", "", $_PREPEND["local"]["ignore"]);
		$_PREPEND["local"]["ignore"] = preg_replace("@/\*\s*.*\s*\*/@mi", "", $_PREPEND["local"]["ignore"]);
		file_put_contents(__DIR__."/.tmp/".$_PREPEND["local"]["tmp_file_name"], $_PREPEND["local"]["ignore"]);
	}
	
	/** Parcourir les règle (text épuré) **/
	$_PREPEND["local"]["ignore"] = fopen(__DIR__."/.tmp/".$_PREPEND["local"]["tmp_file_name"], "r");
	$_PREPEND["local"]["current_level"] = 0;
	$_PREPEND["local"]["accept_level"] = Array(true); // Niveau 0 est toujours admis, car vaut pour le wildcard *
	$_PREPEND["local"]["in_folders_block"] = false;
	$_PREPEND["local"]["in_files_block"] = false;
	
	
	while($_PREPEND["local"]["buffer"] = fgets($_PREPEND["local"]["ignore"])){
		// Supprimer le retour chariot
		$_PREPEND["local"]["buffer"] = str_replace("\n", "", $_PREPEND["local"]["buffer"]);
		
		// RAZ
		$_PREPEND["local"]["skip"] = false;
		
		// Si la ligne n'est pas vide 
		if(!preg_match("#^\s*$#", $_PREPEND["local"]["buffer"])){
			//--//echo "BUFFER :: #".$_PREPEND["local"]["buffer"]."#";
			//--//echo PHP_EOL;
			
			// Vérifier s'il s'agit d'un groupe FOLDER et que celui-ci est approuvé dans son niveau d'imbrication
			if(preg_match("#^\s*FILES\s*\{#i", $_PREPEND["local"]["buffer"]) && $_PREPEND["local"]["accept_level"][$_PREPEND["local"]["current_level"]]){
				//--//echo "--> BLOCK FILE FOUND & LEVEL ALLOW;".PHP_EOL;
				$_PREPEND["local"]["in_files_block"] = true;
				$_PREPEND["local"]["skip"] = true;
			}
			
			// Vérifier s'il s'agit d'un groupe FILE
			if(preg_match("#^\s*FOLDERS\s*\{#i", $_PREPEND["local"]["buffer"])){
				//--//echo "--> BLOCK FOLDER FOUND & LEVEL ALLOW;".PHP_EOL;
				$_PREPEND["local"]["in_folders_block"] = true;
				$_PREPEND["local"]["skip"] = true;
			}
			
			// Vérifier s'il s'agit d'un groupe d'analyse
			if(preg_match("#^\s*([a-zA-Z_]+)\s+([a-zA-Z0-9-_.\s\/\(\);,=:\*^$?~]+)\s+\{#", $_PREPEND["local"]["buffer"], $_PREPEND["local"]["matches"])){
				//--//echo "--> INSTRUCTION FOUND;".PHP_EOL;
				//--//echo "--> INCREASE LEVEL FROM ".$_PREPEND["local"]["current_level"];
				
				// On entre dans un niveau/sous-niveau
				$_PREPEND["local"]["current_level"]++;
				
				//--//echo " to ".$_PREPEND["local"]["current_level"].PHP_EOL;
				
				// Translation de variable pour "simplifier"
				$_PREPEND["local"]["key"] = $_PREPEND["local"]["matches"][1];
				$_PREPEND["local"]["value"] = $_PREPEND["local"]["matches"][2];
				
				if(array_key_exists($_PREPEND["local"]["key"], $_SERVER)){
					//--//echo '--> KEY EXIST IN $_SERVER;'.PHP_EOL;
					
					// Si la valeur commence par un tilde, alors RegExp
					if(preg_match("#^~#", $_PREPEND["local"]["value"])){
						//--//echo "--> VALUE READ AS REGEXP;".PHP_EOL;
						// Suppression du tilde
						$_PREPEND["local"]["value"] = preg_replace("#^~#", "", $_PREPEND["local"]["value"], 1);
						
						if(preg_match("#".$_PREPEND["local"]["value"]."#", $_SERVER[$_PREPEND["local"]["key"]])){
							//--//echo '--> $_SERVER VALUE MATCH WITH REGEXP PATTERN;';
							$_PREPEND["local"]["accept_level"][$_PREPEND["local"]["current_level"]] = true;
						} else {
							//--//echo '--> $_SERVER VALUE NOT MATCH WITH REGEXP PATTERN;';
							$_PREPEND["local"]["accept_level"][$_PREPEND["local"]["current_level"]] = false;
						}
					}
					// Sinon si la valeur est exactement celle attendue
					else if($_SERVER[$_PREPEND["local"]["key"]] == $_PREPEND["local"]["value"]){
						//--//echo "--> VALUE READ AS STRING;".PHP_EOL;
						//--//echo "--> VALUE EXACTLY EQUAL;".PHP_EOL;
						$_PREPEND["local"]["accept_level"][$_PREPEND["local"]["current_level"]] = true;
					}
					// Sinon ignorée le block (niveau faux)
					else {
						$_PREPEND["local"]["accept_level"][$_PREPEND["local"]["current_level"]] = false;
					}
				} else {
					$_PREPEND["local"]["accept_level"][$_PREPEND["local"]["current_level"]] = false;
				}
				
				$_PREPEND["local"]["skip"] = true;
			}
			
			// Vérifier s'il s'agit d'une fermeture de block
			if(preg_match("#^\s*\}\s*$#", $_PREPEND["local"]["buffer"])){
				//--//echo "--> BLOCK END FOUND - CLOSE LEVEL;".PHP_EOL;
				
				// Gestion de niveau s'il ne s'agissait pas d'un block FOLDERS ou FILES
				if(!$_PREPEND["local"]["in_files_block"] && !$_PREPEND["local"]["in_folders_block"]){
					$_PREPEND["local"]["accept_level"] = false;
					$_PREPEND["local"]["current_level"]--;
				}
				
				
				// Si nous etions dans un block FILES ou FOLDERS, il est cloturé, on n'enregistre plus
				if($_PREPEND["local"]["in_files_block"]) $_PREPEND["local"]["in_files_block"] = false;
				if($_PREPEND["local"]["in_folders_block"]) $_PREPEND["local"]["in_folders_block"] = false;
				$_PREPEND["local"]["skip"] = true;
			}
			
			// Traitement 
			if(!$_PREPEND["local"]["skip"]){
				//--//echo "--> TEXT LINE, PROCESS;".PHP_EOL;
				
				$_PREPEND["local"]["to_ignore"] = preg_match("#^\s*(.*)\s*$#i", $_PREPEND["local"]["buffer"], $_PREPEND["local"]["matches"]);
				
				// Permet d'ignorer les lignes vide entre deux valeur au sein du block
				if(count($_PREPEND["local"]["matches"]) >= 2){
					//--//echo "--> CHECK FOR EXCLUSION;".PHP_EOL;
					$regexp_store = "";
					
					if(preg_match("#^~#", $_PREPEND["local"]["matches"][1])){
						$regexp_store = "_regexp";
						$_PREPEND["local"]["matches"][1] = preg_replace("#^~#", "", $_PREPEND["local"]["matches"][1]);
					}
					
					if($_PREPEND["local"]["in_files_block"]){
						//--//echo "--> LINE ADD TO FILES EXCLUSION;".PHP_EOL;
						$_PREPEND["ignore"]["files".$regexp_store][] = $_PREPEND["local"]["matches"][1];
					}
					else if($_PREPEND["local"]["in_folders_block"]){
						//--//echo "--> LINE ADD TO FOLDERS EXCLUSION;".PHP_EOL;
						$_PREPEND["ignore"]["folders".$regexp_store][] = $_PREPEND["local"]["matches"][1];
					} else {
						//--//echo "--> LINE SKIPPED;".PHP_EOL;
					}
				}
				
			}
			
			//--//echo PHP_EOL;
			//--//echo PHP_EOL;
		}
	}
	
	fclose($_PREPEND["local"]["ignore"]);
}



/** -------------------------- **/
/** --- Lecture du dossier --- **/
/** -------------------------- **/
function apf_read_folder($full_path){
	global $_PREPEND;
	$folders = scandir($full_path);
	
	/** Parcourir le résultat obtenu **/
	foreach($folders as $fkey => $fvalue){
		/** Si l'entrée ne commence pas par un point **/
		if(!preg_match("#^\.#", $fvalue)){
			/** S'il s'agit d'un dossier **/
			if(is_dir("$full_path/$fvalue")){
				/** S'il n'est pas explicitement exclus **/
				if(!in_array($fvalue, $_PREPEND["ignore"]["folders"])){
					$allowed = true;
					
					/** S'il n'est pas exclus par RegExp **/
					foreach($_PREPEND["ignore"]["folders_regexp"] as $exclude_pattern){
						if(preg_match("#$exclude_pattern#", $fvalue)){
							$allowed = false;
							break;
						}
					}
					
					if($allowed) apf_read_folder("$full_path/$fvalue");
				}
			}
			/** S'il s'agit d'un fichier **/
			else if (is_file("$full_path/$fvalue")){
				/** S'il n'est pas explicitement exclus **/
				if(!in_array($fvalue, $_PREPEND["ignore"]["files"])){
					$allowed = true;
					
					/** S'il n'est pas exclus par RegExp **/
					foreach($_PREPEND["ignore"]["files_regexp"] as $exclude_pattern){
						if(preg_match("#$exclude_pattern#", $fvalue)){
							$allowed = false;
							break;
						}
					}
					
					if($allowed) require_once "$full_path/$fvalue";
				}
			}
		}
	}
}


/** -------------------------- **/
/** --- Lecture du dossier --- **/
/** -------------------------- **/
apf_read_folder(__DIR__);


//--//print_r($_PREPEND["ignore"]);
//--//print_r($_PREPEND["folders"]);
//--//print_r($GLOBALS['_PREPEND']);



/** ----------------------------------- **/
/** --- Supprimer toutes les traces --- **/
/** ----------------------------------- **/
@unlink(__DIR__."/.ignore_tmp");
unset($GLOBALS['_PREPEND']);


//--//echo "-- FIN --"; exit();
?>