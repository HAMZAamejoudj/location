<?php
/**
 * Classe Permission
 * Gère les opérations liées aux permissions
 */
class Permission {
    // Connexion
    private $conn;
    private $table_name = "permissions";

    // Propriétés de l'objet
    public $id;
    public $nom;
    public $description;

    /**
     * Constructeur avec connexion à la base de données
     * @param PDO $db Objet de connexion PDO
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une nouvelle permission
     * @return bool Succès ou échec de l'opération
     */
    public function create() {
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . " SET nom=:nom, description=:description";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Nettoyage des données
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->description = htmlspecialchars(strip_tags($this->description));

        // Liaison des valeurs
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":description", $this->description);

        // Exécution de la requête
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Mettre à jour une permission
     * @return bool Succès ou échec de l'opération
     */
    public function update() {
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . " SET nom=:nom, description=:description WHERE id=:id";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Nettoyage des données
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->description = htmlspecialchars(strip_tags($this->description));

        // Liaison des valeurs
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":description", $this->description);

        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }

        return false;
    }

    /**
     * Supprimer une permission
     * @return bool Succès ou échec de l'opération
     */
    public function delete() {
        // Requête de suppression
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Nettoyage de l'ID
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Liaison de l'ID
        $stmt->bindParam(1, $this->id);

        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }

        return false;
    }

    /**
     * Lire une permission spécifique
     * @return void
     */
    public function readOne() {
        // Requête de lecture
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Liaison de l'ID
        $stmt->bindParam(1, $this->id);

        // Exécution de la requête
        $stmt->execute();

        // Récupération des données
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Définition des propriétés
        if($row) {
            $this->nom = $row['nom'];
            $this->description = $row['description'];
        }
    }

    /**
     * Lire toutes les permissions
     * @return PDOStatement Résultat de la requête
     */
    public function readAll() {
        // Requête de lecture
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY description";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Exécution de la requête
        $stmt->execute();

        return $stmt;
    }

    /**
     * Récupérer les utilisateurs qui ont une permission spécifique
     * @return PDOStatement Résultat de la requête
     */
    public function getUsers() {
        // Requête de lecture
        $query = "SELECT u.id, u.username, u.nom, u.prenom 
                  FROM users u 
                  JOIN user_permissions up ON u.id = up.user_id 
                  WHERE up.permission_id = ?";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Liaison de l'ID de permission
        $stmt->bindParam(1, $this->id);

        // Exécution de la requête
        $stmt->execute();

        return $stmt;
    }

    /**
     * Vérifier si un nom de permission existe déjà
     * @param string $nom Nom de permission à vérifier
     * @param int $ignore_id ID de permission à ignorer (pour les mises à jour)
     * @return bool True si le nom existe, false sinon
     */
    public function nameExists($nom, $ignore_id = 0) {
        // Requête de vérification
        $query = "SELECT id FROM " . $this->table_name . " WHERE nom = ? AND id != ? LIMIT 0,1";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Liaison des paramètres
        $stmt->bindParam(1, $nom);
        $stmt->bindParam(2, $ignore_id);

        // Exécution de la requête
        $stmt->execute();

        // Vérification s'il y a un résultat
        return ($stmt->rowCount() > 0);
    }
}
?>