<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flotte de Location - Tableau de Bord</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --success: #3cd458;
            --warning: #ffc93c;
            --danger: #ff5757;
            --info: #49beff;
            --gray: #f0f2f5;
            --dark: #2b2d42;
            --light: #ffffff;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            
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
            font-family: 'Segoe UI', 'Roboto', sans-serif;
        }
        
        body {
            background-color: #f9fafc;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            height: 100vh;
            background-color: var(--sidebar-bg);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 10;
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
            color: var(--primary);
        }
        
        .menu-item.active {
            background-color: var(--sidebar-hover);
            color: var(--primary);
            font-weight: 500;
            border-left: 3px solid var(--primary);
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
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 25px;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,0.07);
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            position: relative;
            transition: var(--transition);
        }
        
        .tab.active {
            color: var(--primary);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
            border-radius: 3px 3px 0 0;
        }
        
        .dashboard-section {
            background-color: var(--light);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--box-shadow);
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            margin-bottom: 24px;
        }
        
        .flex-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        
        .income-card {
            flex: 1;
            text-align: center;
            padding: 20px;
        }
        
        .income-label {
            color: #777;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .income-value {
            font-size: 36px;
            font-weight: 300;
            color: #333;
        }
        
        .euro {
            font-size: 24px;
            color: #777;
            margin-left: 2px;
        }
        
        .orders-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .order-card {
            flex: 1;
            background-color: var(--gray);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow);
        }
        
        .deliveries {
            color: var(--success);
        }
        
        .collections {
            color: var(--info);
        }
        
        .unassigned {
            color: var(--danger);
        }
        
        .order-label {
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .order-value {
            font-size: 36px;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .alert-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background-color: rgba(255, 87, 87, 0.1);
            color: var(--danger);
            border-radius: 50%;
            font-size: 16px;
            margin-left: 10px;
        }
        
        .button-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .btn {
            padding: 12px;
            background-color: var(--gray);
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #555;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .btn:hover {
            background-color: #e5e7eb;
            color: #333;
        }
        
        .extras-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .extra-card {
            background-color: var(--gray);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .extra-card:hover {
            box-shadow: var(--box-shadow);
            transform: translateY(-3px);
        }
        
        .extra-title {
            text-align: center;
            background-color: #fff;
            padding: 14px;
            font-weight: 600;
            font-size: 14px;
            color: #444;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .status-tabs {
            display: flex;
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .status-tab {
            flex: 1;
            text-align: center;
            font-size: 12px;
            font-weight: 500;
            padding: 10px 0;
        }
        
        .requested {
            color: var(--success);
        }
        
        .returning {
            color: var(--info);
        }
        
        .on-street {
            color: #777;
        }
        
        .stats-row {
            display: flex;
            background-color: #fff;
        }
        
        .stat {
            flex: 1;
            text-align: center;
            padding: 12px 8px;
        }
        
        .stat-value {
            font-size: 20px;
            color: #333;
            font-weight: 500;
        }
        
        .totals-row {
            display: flex;
            background-color: rgba(0,0,0,0.02);
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .total {
            flex: 1;
            text-align: center;
            padding: 12px 8px;
        }
        
        .total-label {
            font-size: 10px;
            color: #777;
            text-transform: uppercase;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .total-value {
            font-size: 15px;
            font-weight: 500;
            color: #333;
        }
        
        .over-value {
            color: var(--danger);
        }
        
        /* Add Car Button */
        .add-car-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .add-car-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
        }
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                order: 2;
            }
            
            .menu-items {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                order: 1;
            }
            
            .flex-container, 
            .orders-container {
                flex-direction: column;
            }
            
            .button-grid {
                grid-template-columns: 1fr;
            }
            
            .extras-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="tabs">
                <div class="tab active">Aujourd'hui</div>
                <div class="tab">Hier</div>
                <div class="tab">Période</div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-title">REVENUS ATTENDUS</div>
                <div class="flex-container">
                    <div class="income-card">
                        <div class="income-label">Espèces Aujourd'hui</div>
                        <div class="income-value">0.00<span class="euro">€</span></div>
                    </div>
                    <div class="income-card">
                        <div class="income-label">Prépayé Aujourd'hui</div>
                        <div class="income-value">0.00<span class="euro">€</span></div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-title">COMMANDES</div>
                <div class="orders-container">
                    <div class="order-card">
                        <div class="order-label deliveries">Livraisons Aujourd'hui</div>
                        <div class="order-value">0</div>
                    </div>
                    <div class="order-card">
                        <div class="order-label collections">Collectes Aujourd'hui</div>
                        <div class="order-value">3</div>
                    </div>
                    <div class="order-card">
                        <div class="order-label unassigned">Commandes Non Assignées</div>
                        <div class="order-value">1<span class="alert-badge">!</span></div>
                    </div>
                </div>
                
                <div class="button-grid">
                    <div class="btn">Télécharger Livraisons Aujourd'hui</div>
                    <div class="btn">Télécharger Collectes Aujourd'hui</div>
                    <div class="btn">Voir Commandes Non Assignées</div>
                    <div class="btn">Télécharger Livraisons Demain</div>
                    <div class="btn">Télécharger Collectes Demain</div>
                    <div class="btn">Voir Calendrier d'Assignation</div>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-title">EXTRAS (AUJOURD'HUI)</div>
                
                <div class="extras-grid">
                    <div class="extra-card">
                        <div class="extra-title">HEURES SUPPLÉMENTAIRES ENTRÉES</div>
                        <div class="status-tabs">
                            <div class="status-tab requested">DEMANDÉS</div>
                            <div class="status-tab returning">RETOURS</div>
                            <div class="status-tab on-street">EN RUE</div>
                        </div>
                        <div class="stats-row">
                            <div class="stat">
                                <div class="stat-value">0.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">0.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">8.0</div>
                            </div>
                        </div>
                        <div class="totals-row">
                            <div class="total">
                                <div class="total-label">TOTAL</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">DISPONIBLE</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">SURPLUS</div>
                                <div class="total-value">0.0</div>
                            </div>
                        </div>
                    </div>
                        <div class="extra-title">SIÈGES BÉBÉ</div>
                        <div class="status-tabs">
                            <div class="status-tab requested">DEMANDÉS</div>
                            <div class="status-tab returning">RETOURS</div>
                            <div class="status-tab on-street">EN RUE</div>
                        </div>
                        <div class="stats-row">
                            <div class="stat">
                                <div class="stat-value">8.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">10.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">62.0</div>
                            </div>
                        </div>
                        <div class="totals-row">
                            <div class="total">
                                <div class="total-label">TOTAL</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">DISPONIBLE</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">SURPLUS</div>
                                <div class="total-value">0.0</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="extra-card">
                        <div class="extra-title">SECOND CHAUFFEUR</div>
                        <div class="status-tabs">
                            <div class="status-tab requested">DEMANDÉS</div>
                            <div class="status-tab returning">RETOURS</div>
                            <div class="status-tab on-street">EN RUE</div>
                        </div>
                        <div class="stats-row">
                            <div class="stat">
                                <div class="stat-value">49.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">47.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">330.0</div>
                            </div>
                        </div>
                        <div class="totals-row">
                            <div class="total">
                                <div class="total-label">TOTAL</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">DISPONIBLE</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">SURPLUS</div>
                                <div class="total-value over-value">2.0</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="extra-card">
                        <div class="extra-title">FRAIS DE VOITURIER</div>
                        <div class="status-tabs">
                            <div class="status-tab requested">DEMANDÉS</div>
                            <div class="status-tab returning">RETOURS</div>
                            <div class="status-tab on-street">EN RUE</div>
                        </div>
                        <div class="stats-row">
                            <div class="stat">
                                <div class="stat-value">0.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">0.0</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value">0.0</div>
                            </div>
                        </div>
                        <div class="totals-row">
                            <div class="total">
                                <div class="total-label">TOTAL</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">DISPONIBLE</div>
                                <div class="total-value">0.0</div>
                            </div>
                            <div class="total">
                                <div class="total-label">SURPLUS</div>
                                <div class="total-value">0.0</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="extra-card">
    <div class="extra-title">SYSTÈME DE NAVIGATION</div>
    <div class="status-tabs">
        <div class="status-tab requested">DEMANDÉS</div>
        <div class="status-tab returning">RETOURS</div>
        <div class="status-tab on-street">EN RUE</div>
    </div>
    <div class="stats-row">
        <div class="stat">
            <div class="stat-value">22.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">18.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">45.0</div>
        </div>
    </div>
    <div class="totals-row">
        <div class="total">
            <div class="total-label">TOTAL</div>
            <div class="total-value">55.0</div>
        </div>
        <div class="total">
            <div class="total-label">DISPONIBLE</div>
            <div class="total-value">33.0</div>
        </div>
        <div class="total">
            <div class="total-label">SURPLUS</div>
            <div class="total-value">0.0</div>
        </div>
    </div>
</div>

<div class="extra-card">
    <div class="extra-title">CHAÎNES NEIGE</div>
    <div class="status-tabs">
        <div class="status-tab requested">DEMANDÉS</div>
        <div class="status-tab returning">RETOURS</div>
        <div class="status-tab on-street">EN RUE</div>
    </div>
    <div class="stats-row">
        <div class="stat">
            <div class="stat-value">5.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">2.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">10.0</div>
        </div>
    </div>
    <div class="totals-row">
        <div class="total">
            <div class="total-label">TOTAL</div>
            <div class="total-value">15.0</div>
        </div>
        <div class="total">
            <div class="total-label">DISPONIBLE</div>
            <div class="total-value">10.0</div>
        </div>
        <div class="total">
            <div class="total-label">SURPLUS</div>
            <div class="total-value">0.0</div>
        </div>
    </div>
</div>

<div class="extra-card">
    <div class="extra-title">SIÈGES REHAUSSEURS</div>
    <div class="status-tabs">
        <div class="status-tab requested">DEMANDÉS</div>
        <div class="status-tab returning">RETOURS</div>
        <div class="status-tab on-street">EN RUE</div>
    </div>
    <div class="stats-row">
        <div class="stat">
            <div class="stat-value">12.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">8.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">14.0</div>
        </div>
    </div>
    <div class="totals-row">
        <div class="total">
            <div class="total-label">TOTAL</div>
            <div class="total-value">28.0</div>
        </div>
        <div class="total">
            <div class="total-label">DISPONIBLE</div>
            <div class="total-value">16.0</div>
        </div>
        <div class="total">
            <div class="total-label">SURPLUS</div>
            <div class="total-value">4.0</div>
        </div>
    </div>
</div>

<div class="extra-card">
    <div class="extra-title">CARTES CARBURANT</div>
    <div class="status-tabs">
        <div class="status-tab requested">DEMANDÉS</div>
        <div class="status-tab returning">RETOURS</div>
        <div class="status-tab on-street">EN RUE</div>
    </div>
    <div class="stats-row">
        <div class="stat">
            <div class="stat-value">30.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">25.0</div>
        </div>
        <div class="stat">
            <div class="stat-value">50.0</div>
        </div>
    </div>
    <div class="totals-row">
        <div class="total">
            <div class="total-label">TOTAL</div>
            <div class="total-value">80.0</div>
        </div>
        <div class="total">
            <div class="total-label">DISPONIBLE</div>
            <div class="total-value">50.0</div>
        </div>
        <div class="total">
            <div class="total-label">SURPLUS</div>
            <div class="total-value">0.0</div>
        </div>
    </div>
</div>