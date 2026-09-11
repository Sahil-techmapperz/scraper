<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace & Job Live Data Explorer | OLX, CarDekho, Naukri.com & Cashify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: rgba(23, 32, 51, 0.7);
            --bg-card-hover: rgba(30, 41, 67, 0.9);
            --border-color: rgba(255, 255, 255, 0.08);
            --border-active: rgba(99, 102, 241, 0.4);
            --accent-primary: #6366f1;
            --accent-secondary: #06b6d4;
            --accent-gradient: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
            --accent-glow: rgba(99, 102, 241, 0.25);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-card: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
            background-image:
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
        }

        /* Top Navigation Header */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(10, 14, 23, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
        }

        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary);
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: var(--accent-gradient);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            box-shadow: 0 4px 15px var(--accent-glow);
        }

        .brand-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-text p {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.4;
                transform: scale(0.85);
            }
        }

        .btn-docs {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-docs:hover {
            color: var(--text-primary);
            border-color: var(--accent-primary);
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Filter Control Panel */
        .search-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            backdrop-filter: blur(12px);
            box-shadow: var(--shadow-card);
            margin-bottom: 2rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            background: rgba(10, 14, 23, 0.7);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            padding: 10px 14px 10px 38px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        select.form-control {
            appearance: none;
            cursor: pointer;
        }

        .panel-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.25rem;
        }

        .api-key-group {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .api-key-input {
            background: rgba(10, 14, 23, 0.7);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            font-size: 0.8rem;
            font-family: 'JetBrains Mono', monospace;
            color: var(--accent-secondary);
            width: 220px;
        }

        .btn-search {
            background: var(--accent-gradient);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px var(--accent-glow);
            transition: var(--transition);
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(99, 102, 241, 0.4);
        }

        /* Metrics Bar */
        .metrics-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .metrics-tags {
            display: flex;
            gap: 10px;
        }

        .metric-tag {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-family: 'JetBrains Mono', monospace;
        }

        .metric-tag span {
            color: var(--accent-secondary);
            font-weight: 600;
        }

        /* Listings Grid */
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .listing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .listing-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-active);
            box-shadow: var(--shadow-card);
            background: var(--bg-card-hover);
        }

        .image-container {
            width: 100%;
            height: 210px;
            background: #060911;
            position: relative;
            overflow: hidden;
        }

        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .listing-card:hover .image-container img {
            transform: scale(1.05);
        }

        .image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            gap: 8px;
        }

        .price-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(10, 14, 23, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 4px 12px;
            border-radius: var(--radius-sm);
        }

        .category-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(99, 102, 241, 0.85);
            backdrop-filter: blur(8px);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 9999px;
            text-transform: capitalize;
        }

        .card-body {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            gap: 10px;
        }

        .listing-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            min-height: 2.8em;
        }

        .listing-location {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .card-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 10px;
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .btn-view {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--accent-secondary);
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-view:hover {
            background: var(--accent-secondary);
            color: black;
        }

        /* XYZFinders Sync & Database Push Styles */
        .btn-push-all {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            padding: 8px 18px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-push-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
        }

        .btn-push-single {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
            padding: 5px 11px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .btn-push-single:hover {
            background: #10b981;
            color: white;
        }

        .btn-site-link {
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-site-link:hover {
            background: var(--accent-primary);
            color: white;
        }

        .toast-notify {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            background: rgba(16, 185, 129, 0.95);
            backdrop-filter: blur(12px);
            color: white;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            animation: slideUpToast 0.3s ease-out;
        }

        @keyframes slideUpToast {
            from {
                transform: translateY(100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Job Specific Styling */
        .job-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
        }

        .job-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-active);
            box-shadow: var(--shadow-card);
            background: var(--bg-card-hover);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .job-company {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--accent-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .rating-badge {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .job-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .job-meta-item i {
            color: var(--accent-primary);
        }

        .skill-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .skill-chip {
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.25);
            color: #c7d2fe;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 9999px;
            font-weight: 500;
        }

        .btn-apply {
            background: var(--accent-gradient);
            border: none;
            color: white;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-apply:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        /* Detail Modal */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 1.5rem;
        }

        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-card);
            position: relative;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: white;
        }

        .modal-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }

        .gallery-img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        .description-box {
            background: rgba(10, 14, 23, 0.6);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 1.25rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            white-space: pre-line;
        }

        /* Loading Spinner */
        .spinner-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 5rem 0;
            gap: 1rem;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(99, 102, 241, 0.2);
            border-top-color: var(--accent-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* JSON Drawer Toggle */
        .json-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .json-drawer {
            display: none;
            background: #060911;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1rem;
            max-height: 400px;
            overflow: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: #38bdf8;
        }
    </style>
</head>

<body>

    <header>
        <div class="header-inner">
            <a href="/" class="brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="brand-text">
                    <h1>Marketplace & Job Live Data Explorer</h1>
                    <p>Powered by CodeIgniter 4 & Python Engine (OLX, CarDekho, Naukri & Cashify)</p>
                </div>
            </a>
            <div class="header-actions">
                <div class="status-pill">
                    <div class="pulse-dot"></div>
                    Live API Connected
                </div>
                <a href="/api/docs" target="_blank" class="btn-docs">
                    <i class="fa-solid fa-book"></i>
                    API Docs
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Filter Controls -->
        <section class="search-panel">
            <div class="search-grid">
                <div class="form-group">
                    <label>Platform / Source</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-server"></i>
                        <select id="filter-source" class="form-control" onchange="onSourceChange()">
                            <option value="olx">OLX India</option>
                            <option value="cardekho">CarDekho Cars</option>
                            <option value="naukri" selected>Naukri.com Jobs</option>
                            <option value="cashify">Cashify Refurbished Gadgets</option>
                        </select>
                    </div>
                </div>


                <div class="form-group" id="group-category">
                    <label id="label-category">Category / Role</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-list"></i>
                        <select id="filter-category" class="form-control" onchange="updateTopSiteLink()">
                            <option value="jobs" selected>All Tech & Corporate Jobs</option>
                            <option value="software-engineer">Software Engineer / Developer</option>
                            <option value="data-scientist">Data Science & AI / ML</option>
                            <option value="devops-engineer">DevOps & Cloud Engineer</option>
                            <option value="product-manager">Product & Project Manager</option>
                            <option value="frontend-developer">Frontend / UI Developer</option>
                            <option value="backend-developer">Backend Developer</option>
                            <option value="full-stack-developer">Full Stack Engineer</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="group-experience">
                    <label>Experience (Yrs)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-briefcase"></i>
                        <select id="filter-experience" class="form-control">
                            <option value="">Any Experience</option>
                            <option value="0">Fresher / 0 Yrs</option>
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="5">5 Years</option>
                            <option value="7">7+ Years</option>
                            <option value="10">10+ Years</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>City / Location</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-location-dot"></i>
                        <input type="text" id="filter-city" class="form-control" placeholder="e.g. Bangalore, Delhi, Hyderabad, Pune, Mumbai" value="Bangalore">
                    </div>
                </div>

                <div class="form-group">
                    <label>Keyword / Search</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="filter-keyword" class="form-control" placeholder="e.g. Maruti, iPhone, 2 BHK">
                    </div>
                </div>

                <div class="form-group">
                    <label>Sort By</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-arrow-down-wide-short"></i>
                        <select id="filter-sort" class="form-control">
                            <option value="newest">Newest First</option>
                            <option value="price_low_to_high">Price: Low to High</option>
                            <option value="price_high_to_low">Price: High to Low</option>
                            <option value="oldest">Oldest First</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label id="label-min-price">Min Price (₹)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-indian-rupee-sign"></i>
                        <input type="number" id="filter-min-price" class="form-control" placeholder="Min ₹ (e.g. 100000)" min="0" step="50000" onkeydown="if(event.key==='Enter') fetchListings()">
                    </div>
                </div>

                <div class="form-group">
                    <label id="label-max-price">Max Price (₹)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-indian-rupee-sign"></i>
                        <input type="number" id="filter-max-price" class="form-control" placeholder="Max ₹ (e.g. 800000)" min="0" step="50000" onkeydown="if(event.key==='Enter') fetchListings()">
                    </div>
                </div>

                <div class="form-group">
                    <label>Results Limit</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-hashtag"></i>
                        <select id="filter-limit" class="form-control">
                            <option value="24">24 items</option>
                            <option value="50" selected>50 items</option>
                            <option value="100">100 items</option>
                            <option value="200">200 items</option>
                            <option value="300">300 items (Max)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="panel-footer">
                <div class="api-key-group">
                    <i class="fa-solid fa-key"></i>
                    <span>API Key:</span>
                    <input type="text" id="api-key" class="api-key-input" value="dev-local-api-key">
                </div>

                <button id="btn-search" class="btn-search" onclick="fetchListings()">
                    <i class="fa-solid fa-bolt"></i>
                    Fetch Live Listings
                </button>
            </div>
        </section>

        <!-- Metrics & Info -->
        <section class="metrics-bar">
            <div id="results-count">Showing 0 listings</div>
            <div class="metrics-tags" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div class="metric-tag">Status: <span id="metric-status">200 OK</span></div>
                <div class="metric-tag">Latency: <span id="metric-latency">0ms</span></div>
                <button class="json-toggle" onclick="toggleJsonDrawer()">
                    <i class="fa-solid fa-code"></i> JSON Response
                </button>
                <button id="btn-push-all" class="btn-push-all" onclick="pushAllToDatabase()" title="Push all extracted listings to XYZFinders Database table">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Push All to DB
                </button>
                <a href="http://localhost:3000/mobiles" target="_blank" class="btn-site-link" id="btn-top-view-site" title="Open XYZFinders Category Page">
                    <i class="fa-solid fa-mobile-screen" id="btn-top-view-icon"></i> <span id="btn-top-view-label">View on Site</span> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px;"></i>
                </a>
            </div>
        </section>

        <pre id="json-drawer" class="json-drawer"></pre>

        <!-- Listings Grid -->
        <div id="listings-container" class="listings-grid"></div>

        <!-- Loading State -->
        <div id="loading-state" class="spinner-container" style="display: none;">
            <div class="spinner"></div>
            <p id="loading-text" style="color: var(--text-muted); font-size: 0.9rem;">Extracting live data...</p>
        </div>
    </main>

    <!-- Detail Modal -->
    <div id="detail-modal" class="modal-backdrop" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div>
                    <h3 id="modal-title" style="font-size: 1.25rem; font-weight: 700; margin-bottom: 4px;"></h3>
                    <div id="modal-price" style="color: var(--accent-secondary); font-size: 1.3rem; font-weight: 800;"></div>
                </div>
                <button class="modal-close" onclick="closeModalDirect()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-gallery" class="gallery-grid"></div>
                <div>
                    <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px;">Description</h4>
                    <div id="modal-desc" class="description-box"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem; flex-wrap: wrap; gap: 10px;">
                    <span id="modal-location" style="color: var(--text-muted); font-size: 0.85rem;"><i class="fa-solid fa-location-dot"></i> </span>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button id="modal-btn-push" class="btn-push-all" onclick="pushSingleToDatabase(currentModalIndex)" style="font-size: 0.85rem; padding: 6px 14px;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Push to DB
                        </button>
                        <a id="modal-link" href="#" target="_blank" class="btn-search" style="font-size: 0.85rem; padding: 6px 16px; text-decoration: none;">
                            <span id="modal-link-text">Open Source</span> <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentData = null;
        let currentModalIndex = 0;
        const XYZFINDERS_API_URL = 'http://localhost:3000/api/external/ingest';

        const categoryOptions = {
            olx: [{
                    value: 'cars',
                    label: 'Cars & Automobiles'
                },
                {
                    value: 'bikes',
                    label: 'Motorcycles & Bikes'
                },
                {
                    value: 'mobile-phones',
                    label: 'Mobile Phones'
                },
                {
                    value: 'real-estate',
                    label: 'Real Estate & Houses'
                }
            ],
            naukri: [{
                    value: 'jobs',
                    label: 'All Tech & Corporate Jobs'
                },
                {
                    value: 'software-engineer',
                    label: 'Software Engineer / Developer'
                },
                {
                    value: 'data-scientist',
                    label: 'Data Science & AI / ML'
                },
                {
                    value: 'devops-engineer',
                    label: 'DevOps & Cloud Engineer'
                },
                {
                    value: 'product-manager',
                    label: 'Product & Project Manager'
                },
                {
                    value: 'frontend-developer',
                    label: 'Frontend / UI Developer'
                },
                {
                    value: 'backend-developer',
                    label: 'Backend Developer'
                },
                {
                    value: 'full-stack-developer',
                    label: 'Full Stack Engineer'
                }
            ],
            cashify: [{
                    value: 'mobile-phones',
                    label: 'Refurbished Mobile Phones'
                },
                {
                    value: 'laptops',
                    label: 'Refurbished Laptops'
                },
                {
                    value: 'smartwatches',
                    label: 'Refurbished Smartwatches'
                },
                {
                    value: 'tablets',
                    label: 'Refurbished Tablets'
                },
                {
                    value: 'accessories',
                    label: 'Audio & Accessories'
                },
                {
                    value: 'gaming-consoles',
                    label: 'Gaming Consoles'
                }
            ]
        };

        function onSourceChange() {
            const source = document.getElementById('filter-source').value;
            const categoryGroup = document.getElementById('group-category');
            const categorySelect = document.getElementById('filter-category');
            const categoryLabel = document.getElementById('label-category');
            const expGroup = document.getElementById('group-experience');
            const keywordInput = document.getElementById('filter-keyword');
            const cityInput = document.getElementById('filter-city');
            const minPriceLabel = document.getElementById('label-min-price');
            const maxPriceLabel = document.getElementById('label-max-price');
            const minPriceInput = document.getElementById('filter-min-price');
            const maxPriceInput = document.getElementById('filter-max-price');

            if (source === 'cardekho') {
                categoryGroup.style.opacity = '0.5';
                categoryGroup.style.pointerEvents = 'none';
                expGroup.style.display = 'none';
                keywordInput.placeholder = 'e.g. Swift, Creta, BMW X5, Honda City';
                cityInput.placeholder = 'e.g. Delhi-NCR, Mumbai, Bangalore';
                if (cityInput.value === 'Bangalore' || cityInput.value === 'Pan India') cityInput.value = 'delhi-ncr';
                if (minPriceLabel) minPriceLabel.textContent = 'Min Price (₹)';
                if (maxPriceLabel) maxPriceLabel.textContent = 'Max Price (₹)';
                if (minPriceInput) minPriceInput.placeholder = 'Min ₹ (e.g. 200000)';
                if (maxPriceInput) maxPriceInput.placeholder = 'Max ₹ (e.g. 1500000)';
            } else if (source === 'naukri') {
                categoryGroup.style.opacity = '1';
                categoryGroup.style.pointerEvents = 'auto';
                categoryLabel.textContent = 'Job Role / Domain';
                expGroup.style.display = 'block';
                keywordInput.placeholder = 'e.g. Python, React, FastAPI, Docker, AWS';
                cityInput.placeholder = 'e.g. Bangalore, Hyderabad, Pune, Delhi-NCR, Mumbai';
                if (!cityInput.value || cityInput.value === 'delhi-ncr' || cityInput.value === 'Pan India') cityInput.value = 'Bangalore';
                if (minPriceLabel) minPriceLabel.textContent = 'Min Salary (₹)';
                if (maxPriceLabel) maxPriceLabel.textContent = 'Max Salary (₹)';
                if (minPriceInput) minPriceInput.placeholder = 'e.g. 600000 (6 LPA)';
                if (maxPriceInput) maxPriceInput.placeholder = 'e.g. 2500000 (25 LPA)';

                // Populate category options
                categorySelect.innerHTML = '';
                categoryOptions.naukri.forEach(opt => {
                    const el = document.createElement('option');
                    el.value = opt.value;
                    el.textContent = opt.label;
                    categorySelect.appendChild(el);
                });
            } else if (source === 'cashify') {
                categoryGroup.style.opacity = '1';
                categoryGroup.style.pointerEvents = 'auto';
                categoryLabel.textContent = 'Device Category';
                expGroup.style.display = 'none';
                keywordInput.placeholder = 'e.g. iPhone 13, MacBook Air, Galaxy S23, iPad';
                cityInput.placeholder = 'e.g. Pan India / Cashify Store';
                cityInput.value = 'Pan India';
                if (minPriceLabel) minPriceLabel.textContent = 'Min Price (₹)';
                if (maxPriceLabel) maxPriceLabel.textContent = 'Max Price (₹)';
                if (minPriceInput) minPriceInput.placeholder = 'Min ₹ (e.g. 10000)';
                if (maxPriceInput) maxPriceInput.placeholder = 'Max ₹ (e.g. 80000)';

                // Populate category options
                categorySelect.innerHTML = '';
                categoryOptions.cashify.forEach(opt => {
                    const el = document.createElement('option');
                    el.value = opt.value;
                    el.textContent = opt.label;
                    categorySelect.appendChild(el);
                });
            } else { // olx
                categoryGroup.style.opacity = '1';
                categoryGroup.style.pointerEvents = 'auto';
                categoryLabel.textContent = 'Category';
                expGroup.style.display = 'none';
                keywordInput.placeholder = 'e.g. Maruti, iPhone, 2 BHK';
                cityInput.placeholder = 'e.g. Delhi, Mumbai, Bangalore';
                if (cityInput.value === 'Pan India') cityInput.value = 'Bangalore';
                if (minPriceLabel) minPriceLabel.textContent = 'Min Price (₹)';
                if (maxPriceLabel) maxPriceLabel.textContent = 'Max Price (₹)';
                if (minPriceInput) minPriceInput.placeholder = 'Min ₹ (e.g. 10000)';
                if (maxPriceInput) maxPriceInput.placeholder = 'Max ₹ (e.g. 500000)';

                // Populate category options
                categorySelect.innerHTML = '';
                categoryOptions.olx.forEach(opt => {
                    const el = document.createElement('option');
                    el.value = opt.value;
                    el.textContent = opt.label;
                    categorySelect.appendChild(el);
                });
            }
            updateTopSiteLink();
            fetchListings();
        }


        async function fetchListings() {
            const container = document.getElementById('listings-container');
            const loading = document.getElementById('loading-state');
            const resultsCount = document.getElementById('results-count');
            const metricLatency = document.getElementById('metric-latency');
            const metricStatus = document.getElementById('metric-status');
            const jsonDrawer = document.getElementById('json-drawer');

            const source = document.getElementById('filter-source').value || 'naukri';
            const platformName = source === 'cardekho' ? 'CarDekho' : (source === 'naukri' ? 'Naukri.com' : (source === 'cashify' ? 'Cashify' : 'OLX India'));
            const loadingText = document.getElementById('loading-text');
            if (loadingText) {
                loadingText.textContent = `Extracting live data from ${platformName}...`;
            }


            const category = document.getElementById('filter-category').value;
            const experience = document.getElementById('filter-experience')?.value;
            const city = document.getElementById('filter-city').value.trim();
            const keyword = document.getElementById('filter-keyword').value.trim();
            const sort = document.getElementById('filter-sort').value;
            const limit = document.getElementById('filter-limit').value;
            const minPrice = document.getElementById('filter-min-price').value.trim();
            const maxPrice = document.getElementById('filter-max-price').value.trim();
            const apiKey = document.getElementById('api-key').value.trim();

            container.innerHTML = '';
            loading.style.display = 'flex';
            jsonDrawer.style.display = 'none';

            const params = new URLSearchParams();
            if (source !== 'cardekho' && category) params.append('category', category);
            if (source === 'naukri' && experience !== '') params.append('experience', experience);
            if (city) params.append('city', city);
            if (keyword) params.append('keyword', keyword);
            if (sort) params.append('sort', sort);
            if (limit) params.append('limit', limit);
            if (minPrice) params.append('min_price', minPrice);
            if (maxPrice) params.append('max_price', maxPrice);

            const startTime = performance.now();

            try {
                const response = await fetch(`/api/v1/${source}/listings?${params.toString()}`, {
                    headers: {
                        'X-API-Key': apiKey,
                        'Accept': 'application/json'
                    }
                });

                const latency = Math.round(performance.now() - startTime);
                metricLatency.textContent = `${latency}ms`;
                metricStatus.textContent = `${response.status} ${response.statusText}`;

                const contentType = response.headers.get('content-type') || '';
                let json;
                if (contentType.includes('application/json')) {
                    json = await response.json();
                } else {
                    const text = await response.text();
                    throw new Error(`Server returned HTTP ${response.status}. Ensure the Python extraction service is running on port 8000.`);
                }

                currentData = json;
                jsonDrawer.textContent = JSON.stringify(json, null, 2);
                loading.style.display = 'none';

                if (!response.ok || !json.success) {
                    const errMsg = json?.error?.message || `API returned HTTP ${response.status}`;
                    container.innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--danger);">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                            <h3>${escapeHtml(errMsg)}</h3>
                            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">${json?.error?.code ? 'Code: ' + escapeHtml(json.error.code) : 'Please check your API key and server logs.'}</p>
                        </div>
                    `;
                    resultsCount.textContent = '0 listings returned';
                    return;
                }

                if (!json.data || json.data.length === 0) {
                    container.innerHTML = `
                        <div style="grid-column: 1/-1; text-align: center; padding: 4rem; color: var(--text-muted);">
                            <i class="fa-solid fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3>No listings found on ${platformName}</h3>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Try adjusting your city, keyword, experience, or role filter.</p>
                        </div>
                    `;
                    resultsCount.textContent = 'Showing 0 listings';
                    return;
                }

                resultsCount.textContent = `Showing ${json.data.length} listings from ${platformName} in ${city || 'India'}`;
                renderListings(json.data, source);

            } catch (error) {
                loading.style.display = 'none';
                metricStatus.textContent = 'Fetch Error';
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--danger);">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h3>Error Connecting to API</h3>
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">${escapeHtml(error.message)}</p>
                    </div>
                `;
            }
        }

        function renderListings(items, source) {
            const container = document.getElementById('listings-container');
            container.innerHTML = '';

            items.forEach((item, index) => {
                const isJob = source === 'naukri' || !!item.job;

                if (isJob) {
                    const card = document.createElement('div');
                    card.className = 'job-card';

                    const company = item.job?.company_name || item.seller?.name || 'Hiring Company';
                    const rating = item.job?.company_rating;
                    const exp = item.job?.experience_required || '0-5 Yrs';
                    const salary = item.job?.salary_text || (item.price?.amount ? `₹ ${Number(item.price.amount).toLocaleString('en-IN')}` : 'Not Disclosed');
                    const loc = [item.location?.locality, item.location?.city].filter(Boolean).join(', ') || 'India';
                    const skills = item.job?.skills || [];
                    const posted = item.job?.posted_age || item.listing_date || 'Recently';
                    const applyUrl = item.job?.apply_url || item.listing_url || '#';

                    const ratingHtml = rating ? `<span class="rating-badge"><i class="fa-solid fa-star"></i> ${rating}</span>` : '';

                    const skillsHtml = skills.slice(0, 5).map(s => `<span class="skill-chip">${escapeHtml(s)}</span>`).join('');

                    card.innerHTML = `
                        <div class="job-header">
                            <div>
                                <h3 class="listing-title" style="min-height: auto; margin-bottom: 4px;" title="${escapeHtml(item.title || '')}">${escapeHtml(item.title || 'Job Opening')}</h3>
                                <div class="job-company">
                                    <i class="fa-regular fa-building"></i> ${escapeHtml(company)} ${ratingHtml}
                                </div>
                            </div>
                        </div>

                        <div class="job-meta-row">
                            <div class="job-meta-item"><i class="fa-solid fa-briefcase"></i> ${escapeHtml(exp)}</div>
                            <div class="job-meta-item"><i class="fa-solid fa-indian-rupee-sign"></i> ${escapeHtml(salary)}</div>
                            <div class="job-meta-item"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(loc)}</div>
                        </div>

                        ${skillsHtml ? `<div class="skill-chips">${skillsHtml}</div>` : ''}

                        <div class="card-footer">
                            <span><i class="fa-regular fa-clock"></i> ${escapeHtml(posted)}</span>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <button class="btn-push-single" onclick="pushSingleToDatabase(${index})" title="Push job to XYZFinders DB">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Push DB
                                </button>
                                <button class="btn-view" onclick="openDetail(${index})">
                                    Details <i class="fa-solid fa-angle-right"></i>
                                </button>
                                <a href="${escapeHtml(applyUrl)}" target="_blank" class="btn-apply">
                                    Apply <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </div>
                        </div>
                    `;
                    container.appendChild(card);
                } else {
                    // Regular listing (OLX, CarDekho, or Cashify)
                    const card = document.createElement('div');
                    card.className = 'listing-card';

                    const isCashify = source === 'cashify' || !!item.electronics;
                    let priceDisplay = item.price && item.price.amount ?
                        `₹ ${Number(item.price.amount).toLocaleString('en-IN')}` :
                        'Price on Request';

                    if (isCashify && item.electronics?.original_price && item.price?.amount && item.electronics.original_price > item.price.amount) {
                        const disc = item.electronics.discount ? ` (${item.electronics.discount}% OFF)` : '';
                        priceDisplay += `<span style="font-size: 0.75rem; text-decoration: line-through; opacity: 0.6; margin-left: 6px;">₹${Number(item.electronics.original_price).toLocaleString('en-IN')}</span><span style="font-size: 0.75rem; color: #10b981; margin-left: 4px;">${disc}</span>`;
                    }

                    let firstImage = null;
                    if (item.images && item.images.length > 0) {
                        const rawImg = item.images[0];
                        firstImage = typeof rawImg === 'string' ? rawImg : (rawImg.url || null);
                    }

                    const locationText = [item.location?.locality, item.location?.city, item.location?.state]
                        .filter(Boolean)
                        .join(', ') || (isCashify ? 'Cashify Certified Store' : 'India');

                    const imageHtml = firstImage ?
                        `<img src="${firstImage}" alt="${escapeHtml(item.title || 'Listing')}" loading="lazy">` :
                        `<div class="image-placeholder"><i class="fa-regular fa-image" style="font-size: 2rem;"></i><span>No Image</span></div>`;

                    let categoryBadge = '';
                    if (isCashify) {
                        const gradeText = item.electronics?.grade || 'Refurbished';
                        categoryBadge = `<span class="category-badge" style="background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4);">${escapeHtml(gradeText)}</span>`;
                    } else if (item.automobile?.fuel_type) {
                        categoryBadge = `<span class="category-badge">${item.automobile.fuel_type}</span>`;
                    } else if (item.subcategory || item.category) {
                        categoryBadge = `<span class="category-badge">${item.subcategory || item.category}</span>`;
                    }

                    let metaFooter = item.automobile?.year || item.listing_date || 'Recent';
                    if (isCashify) {
                        metaFooter = item.electronics?.warranty || 'Cashify Warranty';
                    }

                    card.innerHTML = `
                        <div class="image-container">
                            ${imageHtml}
                            <div class="price-badge">${priceDisplay}</div>
                            ${categoryBadge}
                        </div>
                        <div class="card-body">
                            <h3 class="listing-title" title="${escapeHtml(item.title || '')}">${escapeHtml(item.title || 'Untitled Listing')}</h3>
                            <div class="listing-location">
                                <i class="fa-solid fa-location-dot"></i>
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(locationText)}</span>
                            </div>
                            <div class="card-footer">
                                <span style="font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;">${escapeHtml(metaFooter)}</span>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <button class="btn-push-single" onclick="pushSingleToDatabase(${index})" title="Push to XYZFinders Database">
                                        <i class="fa-solid fa-cloud-arrow-up"></i> Push DB
                                    </button>
                                    <button class="btn-view" onclick="openDetail(${index})">
                                        Details <i class="fa-solid fa-angle-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    container.appendChild(card);
                }
            });
        }

        function openDetail(index) {
            if (!currentData || !currentData.data || !currentData.data[index]) return;
            currentModalIndex = index;
            const item = currentData.data[index];

            document.getElementById('modal-title').textContent = item.title || 'Listing Detail';

            if (item.job) {
                document.getElementById('modal-price').textContent = item.job.salary_text || (item.price?.amount ? `₹ ${Number(item.price.amount).toLocaleString('en-IN')}` : 'Salary Not Disclosed');
            } else {
                document.getElementById('modal-price').textContent = item.price && item.price.amount ?
                    `₹ ${Number(item.price.amount).toLocaleString('en-IN')}` :
                    'Price on Request';
            }

            document.getElementById('modal-desc').textContent = item.description || 'No detailed description provided.';
            document.getElementById('modal-location').innerHTML = `<i class="fa-solid fa-location-dot"></i> ${[item.location?.locality, item.location?.city, item.location?.state].filter(Boolean).join(', ')}`;

            const platform = (item.source || currentData?.source?.platform || document.getElementById('filter-source')?.value || 'source').toLowerCase();
            const platformLabel = platform === 'cardekho' ? 'CarDekho' : (platform === 'naukri' ? 'Naukri.com' : (platform === 'cashify' ? 'Cashify' : 'OLX'));
            const linkTextEl = document.getElementById('modal-link-text');
            if (linkTextEl) {
                linkTextEl.textContent = platform === 'naukri' ? 'Apply on Naukri.com' : `Open on ${platformLabel}`;
            }
            document.getElementById('modal-link').href = item.job?.apply_url || item.listing_url || '#';

            const gallery = document.getElementById('modal-gallery');
            gallery.innerHTML = '';
            if (item.images && item.images.length > 0) {
                item.images.forEach(img => {
                    const imgUrl = typeof img === 'string' ? img : (img.url || null);
                    if (imgUrl) {
                        const imgEl = document.createElement('img');
                        imgEl.src = imgUrl;
                        imgEl.className = 'gallery-img';
                        gallery.appendChild(imgEl);
                    }
                });
            } else if (item.job && item.job.skills && item.job.skills.length > 0) {
                const skillsWrap = document.createElement('div');
                skillsWrap.style.gridColumn = '1/-1';
                skillsWrap.innerHTML = `
                    <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Key Skills & Technologies</h4>
                    <div class="skill-chips">${item.job.skills.map(s => `<span class="skill-chip" style="font-size: 0.8rem; padding: 4px 10px;">${escapeHtml(s)}</span>`).join('')}</div>
                `;
                gallery.appendChild(skillsWrap);
            }

            document.getElementById('detail-modal').style.display = 'flex';
        }

        function closeModal(event) {
            if (event.target.id === 'detail-modal') {
                closeModalDirect();
            }
        }

        function closeModalDirect() {
            document.getElementById('detail-modal').style.display = 'none';
        }

        function toggleJsonDrawer() {
            const drawer = document.getElementById('json-drawer');
            drawer.style.display = drawer.style.display === 'block' ? 'none' : 'block';
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [m];
            });
        }

        function showToast(message, isSuccess = true, linkUrl = null, linkText = null) {
            const existing = document.getElementById('toast-notification');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.id = 'toast-notification';
            toast.className = 'toast-notify';
            if (!isSuccess) toast.style.background = 'rgba(239, 68, 68, 0.95)';

            let linkHtml = '';
            if (linkUrl && linkText) {
                linkHtml = `<a href="${linkUrl}" target="_blank" style="color: #ffffff; text-decoration: underline; font-weight: 800; margin-left: 8px; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px;">${linkText} &nearr;</a>`;
            }

            toast.innerHTML = `
                <i class="fa-solid ${isSuccess ? 'fa-circle-check' : 'fa-circle-exclamation'}" style="font-size: 1.3rem;"></i>
                <span>${message}</span>
                ${linkHtml}
            `;

            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.transition = 'opacity 0.5s ease';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }, 6000);
        }

        function getDestinationUrl(source, category = null) {
            if (!category) {
                const catEl = document.getElementById('filter-category');
                category = catEl ? catEl.value : null;
            }
            if (source === 'cardekho') {
                return {
                    url: 'http://localhost:3000/automobiles',
                    label: 'View on Automobiles Page',
                    icon: 'fa-car'
                };
            }
            if (source === 'naukri') {
                return {
                    url: 'http://localhost:3000/jobs',
                    label: 'View on Jobs Page',
                    icon: 'fa-briefcase'
                };
            }
            if (source === 'cashify') {
                if (category === 'mobile-phones') {
                    return {
                        url: 'http://localhost:3000/mobiles',
                        label: 'View on Mobiles Page',
                        icon: 'fa-mobile-screen'
                    };
                }
                return {
                    url: 'http://localhost:3000/gadgets',
                    label: 'View on Gadgets Page',
                    icon: 'fa-laptop'
                };
            }
            if (source === 'olx') {
                if (category === 'cars' || category === 'bikes') {
                    return {
                        url: 'http://localhost:3000/automobiles',
                        label: 'View on Automobiles Page',
                        icon: 'fa-car'
                    };
                }
                if (category === 'mobile-phones') {
                    return {
                        url: 'http://localhost:3000/mobiles',
                        label: 'View on Mobiles Page',
                        icon: 'fa-mobile-screen'
                    };
                }
                if (category === 'real-estate') {
                    return {
                        url: 'http://localhost:3000/real-estate',
                        label: 'View on Real Estate Page',
                        icon: 'fa-house'
                    };
                }
                return {
                    url: 'http://localhost:3000/mobiles',
                    label: 'View on Mobiles Page',
                    icon: 'fa-mobile-screen'
                };
            }
            return {
                url: 'http://localhost:3000/mobiles',
                label: 'View on Mobiles Page',
                icon: 'fa-mobile-screen'
            };
        }

        function updateTopSiteLink() {
            const source = document.getElementById('filter-source')?.value || 'cashify';
            const category = document.getElementById('filter-category')?.value;
            const dest = getDestinationUrl(source, category);
            const linkEl = document.getElementById('btn-top-view-site');
            if (linkEl) {
                linkEl.href = dest.url;
                linkEl.title = dest.label;
                linkEl.innerHTML = `<i class="fa-solid ${dest.icon}"></i> ${dest.label.replace('View on ', 'View ')} <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px;"></i>`;
            }
        }

        async function pushAllToDatabase() {
            if (!currentData || !currentData.data || currentData.data.length === 0) {
                showToast('No extracted listings available to push. Please fetch listings first.', false);
                return;
            }

            const source = document.getElementById('filter-source').value || 'cashify';
            const category = document.getElementById('filter-category')?.value;
            const btn = document.getElementById('btn-push-all');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Pushing...';
            btn.disabled = true;

            try {
                const payload = {
                    provider: source,
                    items: currentData.data
                };

                const res = await fetch(XYZFINDERS_API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();
                if (res.ok && json.success) {
                    const count = json.data?.ingested_count || currentData.data.length;
                    const dest = getDestinationUrl(source, category);
                    showToast(`Successfully pushed ${count} listings to XYZFinders Database!`, true, dest.url, dest.label);
                } else {
                    showToast(`Failed to push: ${json.error || json.message || 'Unknown error'}`, false);
                }
            } catch (err) {
                showToast(`Error connecting to XYZFinders DB Ingest API (${XYZFINDERS_API_URL}): ${err.message}`, false);
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }

        async function pushSingleToDatabase(index) {
            if (!currentData || !currentData.data || !currentData.data[index]) return;
            const item = currentData.data[index];
            const source = document.getElementById('filter-source').value || 'cashify';
            const category = document.getElementById('filter-category')?.value;

            try {
                const payload = {
                    provider: source,
                    items: [item]
                };

                const res = await fetch(XYZFINDERS_API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();
                if (res.ok && json.success) {
                    const dest = getDestinationUrl(source, category);
                    showToast(`"${(item.title || 'Listing').substring(0, 30)}..." pushed to XYZFinders DB!`, true, dest.url, dest.label);
                } else {
                    showToast(`Failed to push: ${json.error || json.message || 'Unknown error'}`, false);
                }
            } catch (err) {
                showToast(`Error connecting to XYZFinders: ${err.message}`, false);
            }
        }

        // Fetch on load
        window.addEventListener('DOMContentLoaded', () => {
            updateTopSiteLink();
            fetchListings();
        });
    </script>
</body>

</html>