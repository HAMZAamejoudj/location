<?php
/**
 * Vérifie si l'utilisateur connecté a une permission spécifique
 * @param string $permission_name Nom de la permission à vérifier
 * @return bool Retourne true si l'utilisateur a la permission, false sinon
 */
function hasPermission($permission_name) {
    // Si l'utilisateur n'est pas connecté, il n'a aucune permission
    if(!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Les administrateurs ont toutes les permissions
    if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
        return true;
    }
    
    // Vérifier si la permission est dans le tableau des permissions de l'utilisateur
    if(isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
        return in_array($permission_name, $_SESSION['user_permissions']);
    }
    
    return false;
}

/**
 * Formater une date pour l'affichage
 * @param string $date Date au format MySQL (YYYY-MM-DD HH:MM:SS)
 * @param bool $show_time Afficher l'heure ou non
 * @return string Date formatée
 */
function formatDate($date, $show_time = true) {
    if(empty($date)) return '';
    
    $timestamp = strtotime($date);
    if($show_time) {
        return date('d/m/Y H:i', $timestamp);
    }
    return date('d/m/Y', $timestamp);
}

/**
 * Retourne la classe CSS pour le badge de statut
 * @param string $status Statut à convertir
 * @return string Classe CSS pour le badge
 */
function getStatusBadgeClass($status) {
    switch($status) {
        case 'En attente':
            return 'primary';
        case 'En cours':
            return 'info';
        case 'Terminée':
            return 'success';
        case 'Facturée':
            return 'warning';
        case 'Payée':
            return 'success';
        case 'Annulée':
            return 'danger';
        case 'Reçue':
            return 'success';
        default:
            return 'secondary';
    }
}