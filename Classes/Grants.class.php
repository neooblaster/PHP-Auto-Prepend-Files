<?php
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ---																																						---
/** --- 											----------------------------------------------- 											---
/** --- 															{ Grants.class.php } 																---
/** --- 											----------------------------------------------- 											---
/** ---																																						---
/** ---		AUTEUR 	: Nicolas DUPRE																												---
/** ---																																						---
/** ---		RELEASE	: xx.xx.2016																													---
/** ---																																						---
/** ---		VERSION	: 1.0																																---
/** ---																																						---
/** ---																																						---
/** --- 														-----------------------------															---
/** --- 															 { C H A N G E L O G } 																---
/** --- 														-----------------------------															---
/** ---																																						---
/** ---																																						---

	A mettre en place un système d'historique interne pour qu'en dev on consult les KEY erronée
	
	A voir pour introduir un caractère "FORCER l'ecriture" de l'attribut pour GROUP & USER
		-> genre un $ ou un & au début ou à la fin
		-> Si présent, stocker dans une table de stockage
		-> si sauvegarde depuis tableau, faut reconnaitre........

/** ---																																						---
/** ---																																						---
/** ---		VERSION 1.0 : xx.xx.2016																												---
/** ---		-------------------------																												---
/** ---			- Première release																													---
/** ---																																						---
/** --- 										-----------------------------------------------------											---
/** --- 											{ L I S T E      D E S      M E T H O D E S } 												---
/** --- 										-----------------------------------------------------											---
/** ---																																						---
/** ---		GETTERS :																																	---
/** ---	    ---------																																	---
/** ---																																						---
/** ---			- [Pub] xxxx																															---
/** ---																																						---
/** ---		SETTERS :																																	---
/** ---	    ---------																																	---
/** ---																																						---
/** ---			- [Pub] xxxx																															---
/** ---																																						---
/** ---		OUTPUTTERS :																																---
/** ---	    ------------																																---
/** ---																																						---
/** ---			- [Pub] read																															---
/** ---			- [Sta] throw_error																													---
/** ---			- [Pub] write																															---
/** ---																																						---
/** ---		WORKERS :																																	---
/** ---	    ---------																																	---
/** ---																																						---
/** ---			- [Pri] grant_level_reader																											---
/** ---																																						---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** -----------------------------------------------------------------------------------------------------------------------

	Gestion des droits :
	--------------------
		
		Les droits de référence (par défault) sont obligatoire pour assurer le bon fonctionnement de sécurité de l'application.
			- Si un droit est défini chez un utilisateur, mais ne l'est pas dans la référence, il est ignoré
			- Si un droit n'existe pas chez l'utilisateur, mais existe dans la référence, il sera défini à sa valeur par défaut
			
		La réfénce permet la gestion d'alias sans pour autant surchargé la donnée "Droits" de l'utilisateur en base.
		Un seul fichier permet une configuartion très souple de l'application
		
		
		Comme dans tous les système existant, on peu trouver trois niveau de droits, du plus large au plus précis :
			- Droits commun par défault
			- Droits d'un groupe, non par défault mais appliqué largement
			- Droits d'une utilisateur, personnalisé au détail
		
		
		La priorité des droits est la suivante:
			Utilisateur > Groupe > Default
		
		
		Au niveau de ces droits, il existe également des niveau
			- Si le niveau 1 n'est pas autorisé, l'ensemble des sous-niveau (2 et +) ne le sont pas non plus par définition
		
		
		Dans le cas ou la configuration utilisateur indique que le droit de niveau 2 est autorisé alors quand son niveau parent dans les droits de groupe ne l'est pas, alors il le surpase
		
		
		L'ordre de lecture et écriture des droits pour l'application doit donc se faire dans l'ordre :
			default : Création de l'ensemble des droits possible et identification des alias
			Group : Application des droits défini par le groupe (récriture des droits de bas en haut(niveau) donc récurif)
			user : Application des droits user défini (seconde récriture des droits)
			alias : Création des alias avec les droits finaux obtenu
			dependences : Update et link



	Déclaration de structure de la chaine JSON :
	--------------------------------------------
		
		? Signifie facultatif
		
		STRUCTURE grant-level
			?@GRANTED: boolean					// Allowed for this level (if undefined, mean true)
			?@DEPENDENCIES: Array				// Allowed only if dependencies are allowed too
			?@HIDDEN: boolean						// This flag let you to hide attribut in an user interface
			?you-sub-level:						// Grant, Another Grant-level or pointer to another Grant/Grant-level
				> boolean 
				> Alias 
				> structure of grant-level	
		FIN STRUCTURE



/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
class Grants {
/** -----------------------------------------------------------------------------------------------------------------------
/** -----------------------------------------------------------------------------------------------------------------------
/** ---																																						---
/** ---															{ C O N S T A N T E S }																---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
	
	
	
/** -----------------------------------------------------------------------------------------------------------------------
/** -----------------------------------------------------------------------------------------------------------------------
/** ---																																						---
/** ---															{ P R O P E R T I E S }																---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
	protected $_alias_stored;					// ARRAY		: Alias stockés à traité pour le finishing
	protected $_dependencies_stored; 		// ARRAY		: Dépendance stockées à traité pour le finishing
	protected $_grants_reference;				// ARRAY		: Modèle de droits de référence (permet de gérer les droits non renseigné chez l'utilisateur et eliminé ceux qui n'existe pas (hack))
	protected $_grants_result;					// ARRAY		: Stack des droits générée à renvoyer (permet l'utilisation dans fonction recursive)
	protected $_linear_grants_reference; 	// ARRAY		: Droits par défaut linéarisé
	protected $_reserved_words;				// ARRAY		: Propriétés reservées
	
	//protected $_linear_grants_group; 		// ARRAY		: Droits du group linéarisé
	//protected $_grants_group_supplied;	// STRING	: Droits du groupe envoyé (sauvegarde) 
	//protected $_grants_user_supplied;		// STRING	: Droits du user envoyé (sauvegarde) 
	
	
/** ----------------------------------------------------------------------------------------------------------------------- 
/** -----------------------------------------------------------------------------------------------------------------------
/** ---																																						---
/** ---														{ C O N S T R U C T E U R S }															---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
	/** ------------------------------------------------------------- **
	/** --- Méthode de construction - Execution à l'instanciation --- **
	/** ------------------------------------------------------------- **/
	function __construct($grants_ref=null){
		/** -------------------------------- **
		/** --- Traitement des majuscule --- **
		/** -------------------------------- **/
		/** Controller l'argument **/
		if($grants_ref === null){
			$this->throw_error('Missing argument for Grants engine. A JSON string is required. Check manual to know more about that string.', E_USER_ERROR);
		}
		
		/** Suppression des commentaires **/
		$grants_ref = preg_replace('#/\*\*.+\*\*/#m', '', $grants_ref);
		
		/** Définition du modèle de droit **/
		$this->_grants_reference = json_decode($grants_ref, true);
		
		if(json_last_error() > 0){
			$this->throw_error(sprintf('The Grant JSON <u>Reference</u> supplied contains syntax error : %s. Please check it first.', json_last_error_msg()), E_USER_ERROR);
		}
		
		
		/** ------------------------------------ **
		/** --- Initialisation des variables --- **
		/** ------------------------------------ **/
		$this->_grants_result = Array();
		$this->_alias_stored = Array();
		$this->_dependencies_stored = Array();
		
		$this->_reserved_words = Array(
			"@GRANTED" => 1,
			"@DEPENDENCIES" => 2,
			"@HIDDEN" => 3
		);
	}
	
	/** ------------------------------------------------------------- **
	/** --- Méthode de déstruction - Execution à la fin du script --- **
	/** ------------------------------------------------------------- **/
	function __destruct(){
		
	}
	
	
	
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ---																																						---
/** ---																{ G E T T E R S }																	---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
	
	
	
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ---																																						---
/** ---																{ S E T T E R S }																	---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
	
	
	
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ---																																						---
/** ---															{ O U T P U T E R S }																---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
	/** ---------------------------------------------------------------------- **
	/** --- Méthode de transcription des droits JSON en donnée de sessions --- **
	/** ---------------------------------------------------------------------- **/
	public function read($user_grants, $group_grants='{}'){
		/** #1. Créer l'ensemble de droit par défaut **/
			/** Conversion déjà effectuer dans __construct **/
			$this->grant_level_reader($this->_grants_reference, '');
	
			/** sauvegarder les droits par défaut linéarisé pour la méthode de sauvegarde **/
			$this->_linear_grants_reference = $this->_grants_result;
			//echo "DEFAULT : "; print_r($this->_grants_result);
		
		
		/** #2. Complétion par les droits customisé du group **/
			/** Conversion de la chaine JSON **/
			$group_grants = json_decode($group_grants, true);
		
			if(json_last_error() > 0){
				$this->throw_error(sprintf('The Grant JSON <u>Group</u> supplied contains syntax error : %s. Please check it first.', json_last_error_msg()), E_USER_ERROR);
			}
		
			/** Réécriture avec les droits du groupe **/
			if(count($group_grants) > 0){
				$this->grant_rewriter($group_grants);
			}
			//echo "\n\nREWRITE GROUP : "; print_r($this->_grants_result);
		
		
		/** #3. Complétion par les droits customisé de l'utilisateur **/
			/** Exception : Si null, alors on considère la chaine suivante : {} **/
			if($user_grants === null){
				$user_grants = '{}';
			}
		
			/** Conversion de la chaine JSON **/
			$user_grants = json_decode($user_grants, true);
		
			if(json_last_error() > 0){
				$this->throw_error(sprintf('The Grant JSON <u>User</u> supplied contains syntax error : %s. Please check it first.', json_last_error_msg()), E_USER_ERROR);
			}
		
			/** Réécriture avec les droits du user **/
			if(count($user_grants) > 0){
				$this->grant_rewriter($user_grants);
			}
			//echo "\n\nREWRITE USER : "; print_r($this->_grants_result);
		
		
		/** #4.1. Finishing avec la complétion des alias **/
			/** Parcourir les alias stocké **/
			foreach($this->_alias_stored as $alias_key => $real_key){
				/** Vérifier si l'alias n'existe pas déjà - Suite évolution de droit ou de configuration **/
				/** Si existe, on ne change rien - un alias ne se sauvegarde pas donc, c'est une clé officielle et l'alias n'a pas été supprimé **/
				if(!array_key_exists($alias_key, $this->_grants_result)){
					/** Vérifier si la clé réelle pointée existe :: substr($value, 1) >> suppression de l'arobase (@)**/
					if(array_key_exists($real_key, $this->_grants_result)){
						$this->_grants_result[$alias_key] = &$this->_grants_result[$real_key];
					}
				}
			}
			//echo "\n\nALIASING : "; print_r($this->_grants_result); //// droit obtenu via alias
		
		
		/** #4.2. Finishing avec l'ajustement selon les dépendances **/
			/** Parcourir les dépendances enregistrées **/
			foreach($this->_dependencies_stored as $key => $dependencies){
				/** S'il y à des dépendences, il est nécessaire que les drois sont accordé également **/
				/** Est soit une chaine ou un tableau **/
				switch(gettype($dependencies)){
					case 'array':
						/** Le control de dépendence n'est nécessaire que si la clé ayant cette dépendance est vrai (sinon c'est forcément faux) **/
						if($this->_grants_result[$key]){
							/** Parcourir les dépendances **/
							foreach($dependencies as $dependence_key){
								/** Controler l'existence de la clé de dépendance **/
								if(array_key_exists($dependence_key, $this->_grants_result)){
									if(!$this->_grants_result[$dependence_key]){
										$this->_grants_result[$key] = false;
										break;
									}
								}
							}
						}
					break;
					
					case 'string':
						/** Le control de dépendence n'est nécessaire que si la clé ayant cette dépendance est vrai (sinon c'est forcément faux) **/
						if($this->_grants_result[$key]){
							/** Controler l'existence de la clé de dépendance **/
							if(array_key_exists($dependencies, $this->_grants_result)){
								/** Si la dépendance est fausse, alors la clé dépendante vaudra finalement faux **/
								if(!$this->_grants_result[$dependencies]){
									$this->_grants_result[$key] = false;
								}
							}
						}
					break;
				}
			}
			//echo "\n\nDEPENDENCIES TO FINAL : "; print_r($this->_grants_result); //exit();
			//echo "\n\nDEF SAVED : "; print_r($this->_linear_grants_reference); exit();
		
		
		return $this->_grants_result;
	}
	
	/** --------------------------------------------------------- **
	/** --- Méthode de génération d'erreur offcielle pour PHP --- **
	/** --------------------------------------------------------- **/
	static function throw_error($message='', $error_level=E_USER_NOTICE){
		/** Taille manixmal des sorties : 1024 bit **/
		$traces = debug_backtrace();
		$backtrace_message = null;
		
		/** Commencer à 1 afin de ne pas tenir compte de la trace de throw_error **/
		for($i = (count($traces) - 1); $i > 0; $i--){
			$trace = $traces[$i];
			$class = $trace['class'];
			$function = $trace['function'];
			$file = $trace['file'];
			$line = $trace['line'];
			
			$trace_message = "<b style='color: #336699;'>$class->$function</b> in <b>$file</b> on line <b style='color: #336699'>$line</b>";
			
			$backtrace_message .= "<br /><b> • FROM  ::</b> $trace_message;";
		}
		
		$backtrace_message = "<b>BACKTRACE ::</b> $backtrace_message";
		
		/** Utiliser un PRE pour l'affichage soigné, mais sécurisé la dispo HTML si l'operateur de control d'erreur @ est utilisé **/
		echo "<pre style='margin: 0 !important; padding: 0 !important; display: inline !important;'>";
		trigger_error("$backtrace_message", E_USER_NOTICE);
		
		// ln = 63 > <b>MESSAGE ::</b>\n • ERROR ::</b> <b style='color: red;'>$message</b>
		if(mb_strlen($message) > (1024 - 56)){
			$message = substr($message, 0, (1024 - 63 - 5));
			$message .= '(...)';
		}
		
		trigger_error("<b>MESSAGE ::</b>\n • ERROR ::</b> <b style='color: red;'>$message</b>", $error_level);
		echo "</pre>";
	}
	
	/** ------------------------------------------------------------------------------------------------------- **
	/** --- Méthode de transcription des droits en donnée de sessions en JSON avec validation des attributs --- **
	/** ------------------------------------------------------------------------------------------------------- **/
	//public function save($grants_array, $is_group=false){
	//	/** Si utilisateur, comparer avec group puis default **/
	//	/** Si group, comparer uniquement avec default **/
	//	/**
	//	
	//		Si un attribut n'existe pas dans le groupe il est hérité depuis default, le controler avec default
	//	
	//	**/
	//	
	//	
	//	/** #1. Si ce n'est pas un group, on compare d'abord les droits indiqué avec le group **/
	//	if(!$is_group){
	//		
	//	}
	//	
	//	/** #2. Dans tous les cas, finir la comparaison avec la référence **/
	//	
	//	
	//	return Array();
	//}
	
	
	
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ----------------------------------------------------------------------------------------------------------------------- 
/** ---																																						---
/** ---																{ W O R K E R S }																	---
/** ---																																						---
/** -----------------------------------------------------------------------------------------------------------------------
/** ----------------------------------------------------------------------------------------------------------------------- **/
	/** ------------------------------------------------------------------ **
	/** --- Méthode de lecture du niveau de droit spécifié (Récursive) --- **
	/** ------------------------------------------------------------------ **/
	private function grant_level_reader($level, $grant_backtrace, $forcibly_false=false){
		/** Rechercher si le niveau et sous niveau sont autorisé et si forcibly_false n'est pas demandé **/
		if(!$forcibly_false){
			foreach($level as $key => $value){
				/** Si "@GRANTED" est défini **/
				if(array_key_exists(strtoupper($key), $this->_reserved_words) && $this->_reserved_words[strtoupper($key)] === 1){
					/** S'il vaut faut ou n'est pas un boolean, on n'autorise rien **/
					if(!$value || gettype($value) !== 'boolean'){
						$forcibly_false = true;
					}
					
					break;
				}
			}
		}
		
		
		/** Définition du droit du niveau en cours de lecture (A condition que grant_backtrace ne soit pas null) **/
		if($grant_backtrace !== ''){
			$this->_grants_result[substr($grant_backtrace, 0, strlen($grant_bactrace) - 1)] = ($forcibly_false) ? false : true;
		}
		
		
		/** Parcourir le niveau **/
		foreach($level as $key => $value){
			/** Traitements des propriété reservée **/
			if(array_key_exists(strtoupper($key), $this->_reserved_words)){
				switch($this->_reserved_words[strtoupper($key)]){
					// Gestion des dépendances (stockage pour analyse ultérieure dédiée)
					case 2:
						$this->_dependencies_stored[substr($grant_backtrace, 0, strlen($grant_bactrace) - 1)] = $value;
					break;
				}
			}
			/** Sinon, analyse de la propriété **/
			else {
				// Identification du type de données (Autorisé : Array, Boolean, String)
				$pty_type = gettype($value);
				
				switch($pty_type){
					// Simple propriété, pas de sous niveau, écriture du droit attribué
					case 'boolean':
						// Attention, si $forcibly_false = true, alors on ignore la valeur et vaut faut (droit parent non accordé)
						$this->_grants_result[$grant_backtrace.$key] = ($forcibly_false) ? false : $value;
					break;
					
					// Une chaine de caractère (normalement, un alias)
					case 'string':
						// Vérifier qu'il s'agit d'un alias 
						if(preg_match('#^@#', $value)){
							$this->_alias_stored[$grant_backtrace.$key] = substr($value, 1);
						}
					break;
					
					// Un tableau, donc un sous niveau (Récursivité)
					case 'array':
						$this->grant_level_reader($value, $grant_backtrace.$key.'.', $forcibly_false);
					break;
				}
			}
		}
	}
	
	/** ---------------------------------------------------------------------- **
	/** --- Fonction de mise à jour des droits à l'aide de droit customisé --- **
	/** ---------------------------------------------------------------------- **/
	private function grant_rewriter($linear_grants){
		/** Il est indispensable d'ordonner les données sous peine d'éffectuer des conflit de droit avec des failles **/
		/** Exemple
			{GRANT.NX: true, GRANT: false}
			NX est plus précis
			Loop 1 -> GRANT sera fixé à vrai, car nécessaire pour avoir accès a NX
			Loop 2 -> On remet GRANT à faux 
			=> Conflit
			Il faut aller du plus large au plus fin dans la précision du droit accordé
		**/
		ksort($linear_grants);
		
		/** Parcourir les droits custom linéarisé (pas de structure JSON comme le défault) **/
		foreach($linear_grants as $key => $value){
			/** Vérifier que le droit existe par défaut **/
			if(array_key_exists($key, $this->_grants_result)){
				/** Exploser le droits afin d'obtenir les droits parent **/
				$grant_tree = explode('.', $key);
				
				/** Controler le droit le plus précis (dernier) **/
				$reliably_key = $grant_tree[count($grant_tree) - 1];
				
				/** Si vaut vrai, alors on DOIT autorisé la branche parente jusqu'au plus haut **/
				if($value){
					$updating = null;
					
					foreach($grant_tree as $key){
						$updating = ($updating === null) ? $key : $updating.'.'.$key;
						
						$this->_grants_result[$updating] = $value;
					}
				}
				/** Si vaut faux, alors on ne met à jour que le droit précis **/
				////// reset tout les sous droit
				else {
					$this->_grants_result[$key] = $value;
				}
			}
		}
	}
}
?>