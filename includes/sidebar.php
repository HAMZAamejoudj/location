<style>
        :root {
            --primary-color: #3a7bd5;
            --sidebar-bg: #fff;
            --sidebar-hover: #f5f8ff;
            --text-color: #333;
            --light-text: #777;
            --border-color: #eaeaea;
            --warning-color: #ff6b6b;
            --accent-color: #ffa64d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #f5f7fb;
        }

        .sidebar {
            width: 280px;
            height: 100vh;
            background-color: var(--sidebar-bg);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .logo-area {
            padding: 20px;
            display: flex;
            align-items: center;
            background-color: #1a1a2e;
            color: white;
        }

        .logo-area img {
            width: 30px;
            margin-right: 12px;
        }

        .logo-area h2 {
            font-size: 18px;
            font-weight: 500;
        }

        .logo-area span {
            font-size: 12px;
            opacity: 0.7;
            margin-left: 4px;
        }

        .user-panel {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .user-panel h3 {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .user-panel p {
            font-size: 14px;
            color: var(--light-text);
        }

        .location-select {
            margin-top: 12px;
            position: relative;
        }

        .location-select select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            appearance: none;
            font-size: 14px;
            color: var(--text-color);
            background-color: white;
        }

        .location-select::after {
            content: '▼';
            font-size: 12px;
            color: var(--light-text);
            position: absolute;
            right: 12px;
            top: 10px;
        }

        .notification-area {
            padding: 12px;
            font-size: 12px;
            background-color: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
        }

        .notification-area p {
            margin-bottom: 4px;
        }

        .notification-red {
            color: var(--warning-color);
        }

        .notification-orange {
            color: var(--accent-color);
        }

        .menu-section {
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .menu-title {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }

        .menu-title i {
            font-size: 12px;
        }

        .menu-items {
            list-style: none;
        }

        .menu-item {
            display: block;
            text-decoration: none;
            padding: 12px 20px 12px 45px;
            font-size: 14px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .menu-item:hover {
            background-color: var(--sidebar-hover);
            color: var(--primary-color);
        }

        .menu-item.active {
            background-color: var(--sidebar-hover);
            color: var(--primary-color);
            font-weight: 500;
            border-left: 3px solid var(--primary-color);
        }

        .menu-item i {
            position: absolute;
            left: 20px;
            font-size: 15px;
            width: 20px;
        }

        .logout-btn {
            margin-top: auto;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            font-size: 14px;
            color: var(--light-text);
            cursor: pointer;
            transition: all 0.2s;
            margin-top: auto;
            text-decoration: none;
        }

        .logout-btn:hover {
            background-color: var(--sidebar-hover);
            color: var(--warning-color);
        }

        .logout-btn i {
            margin-right: 10px;
            font-size: 15px;
        }

        /* Pour les icônes, utilisons Font Awesome */
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
    </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

</head>
<body>

    <div class="sidebar">
        <div class="logo-area">
            <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjZmZmZmZmIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHBhdGggZD0iTTIyLjY4IDcuOTNDMjEuODUgNi41NiAyMSA1Ljc1IDIwIDUuNzVIMTRjLS42NCAwLTEuMjcuMjYtMS43My43M0wxMSA3Ljc1aC0xYy0uNTUgMC0xIC40NS0xIDFzLjQ1IDEgMSAxaDEuNWMuMjggMCAuNS4yMi41LjV2Mi41YzAgLjI4LS4yMi41LS41LjVoLTVjLS44MiAwLTEuNTUtLjM3LTIuMDQtLjk2TDMuMyAxMS4xM2MtLjcxLS44NC0xLjgtMS4zLTIuOTUtMS4xNGwtLjE5LjAzYy0uMDUuMDEtLjkuMTgtLjE2LjM1di44NWMwIC41Ni4xOCAxLjA4LjQ5IDEuNTJsMy45MyA1LjE5YTQuOTggNC45OCAwIDAgMCAzLjk1IDEuOThoNi4xM2MxLjQyIDAgMi44OS0uNiAzLjg5LTEuNTlsMy44LTMuOGMxLjU5LTEuNTkgMS41OS00LjE5IDAtNS43OGwtMi41Mi0yLjUzWiIvPjwvc3ZnPg==" alt="Logo">
            <h2>Flotte de Location<span>v3.2.4</span></h2>
        </div>

        <div class="user-panel">
            <h3>Panneau de Réception</h3>
            <p>Yousra El najmi</p>
            <div class="location-select">
                <select>
                    <option>Casablanca Centre-ville</option>
                    <option>Rabat Centre</option>
                    <option>Marrakech Aéroport</option>
                </select>
            </div>
        </div>

        <div class="notification-area">
            <p class="notification-red">Dernière Affectation il y a 12 heure(s)</p>
            <p class="notification-red">Dernière Synchronisation il y a 12 heure(s)</p>
            <p class="notification-orange">Il y a 1 Commande(s) Non Affectée(s)</p>
        </div>

        <div class="menu-section">
            <div class="menu-title">
                Principal <i class="fas fa-chevron-down"></i>
            </div>
            <ul class="menu-items">
                <a href="./accueil.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    Accueil
                </a>
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    Tableau de Bord
                </a>
                <a href="./calendrier-affectation.php" class="menu-item">
                    <i class="far fa-calendar-alt"></i>
                    Calendrier d'Affectation
                </a>
            </ul>
        </div>

        <div class="menu-section">
            <div class="menu-title">
                Commandes <i class="fas fa-chevron-down"></i>
            </div>
            <ul class="menu-items">
                <a href="./nouvelle-commande.php" class="menu-item">
                    <i class="fas fa-plus"></i>
                    Nouvelle Commande
                </a>
                <a href="./livraisons.php" class="menu-item">
                    <i class="fas fa-truck"></i>
                    Livraisons
                </a>
                <a href="./collectes.php" class="menu-item">
                    <i class="fas fa-exchange-alt"></i>
                    Collectes
                </a>
                <a href="./en-circulation.php" class="menu-item">
                    <i class="fas fa-road"></i>
                    En Circulation
                </a>
                <a href="./recentes.php" class="menu-item">
                    <i class="fas fa-history"></i>
                    Récentes
                </a>
                <a href="./toutes.php" class="menu-item">
                    <i class="fas fa-list"></i>
                    Toutes
                </a>
            </ul>
        </div>

        <div class="menu-section">
            <div class="menu-title">
                Flotte <i class="fas fa-chevron-down"></i>
            </div>
            <ul class="menu-items">
                <a href="voitures/index.php" class="menu-item">
                    <i class="fas fa-car"></i>
                    Voitures
                </a>
                <a href="./location-courte-duree.php" class="menu-item">
                    <i class="fas fa-file-contract"></i>
                    Location Courte Durée
                </a>
                <a href="./hors-base.php" class="menu-item">
                    <i class="fas fa-warehouse"></i>
                    Hors Base
                </a>
                <a href="./hors-service.php" class="menu-item">
                    <i class="fas fa-tools"></i>
                    Hors Service
                </a>
                <a href="./duree-location.php" class="menu-item">
                    <i class="far fa-clock"></i>
                    Durée de Location
                </a>
                <a href="./mouvements.php" class="menu-item">
                    <i class="fas fa-exchange-alt"></i>
                    Mouvements de Véhicules
                </a>
            </ul>
        </div>

        <div class="menu-section">
            <div class="menu-title">
                Contrôles <i class="fas fa-chevron-down"></i>
            </div>
            <ul class="menu-items">
                <a href="./parametres.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    Paramètres
                </a>
                <a href="./utilisateurs.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    Utilisateurs
                </a>
            </ul>
        </div>

        <a href="./deconnexion.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            Déconnexion
        </a>
    </div>
