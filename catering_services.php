<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catering Services Packages</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            padding: 40px 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        h1 {
            color: #0078d7;
            font-weight: 300;
            font-size: 42px;
            letter-spacing: -0.5px;
            margin-bottom: 15px;
        }
        
        .subtitle {
            color: #666;
            font-size: 18px;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .metro-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .metro-tile {
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .metro-tile:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.15);
        }
        
        .tile-header {
            padding: 25px 25px 15px;
            position: relative;
        }
        
        .tile-basic .tile-header {
            background: linear-gradient(135deg, #00b294, #00796b);
        }
        
        .tile-family .tile-header {
            background: linear-gradient(135deg, #0078d7, #0063b1);
        }
        
        .tile-professional .tile-header {
            background: linear-gradient(135deg, #8661c5, #5c2d91);
        }
        
        .tile-deluxe .tile-header {
            background: linear-gradient(135deg, #e3008c, #9f0063);
        }
        
        .tile-wedding .tile-header {
            background: linear-gradient(135deg, #ff8c00, #d83b01);
        }
        
        .tile-commercial .tile-header {
            background: linear-gradient(135deg, #107c10, #004b1c);
        }
        
        .tile-name {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 500;
            color: white;
        }
        
        .tile-duration {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 400;
            display: flex;
            align-items: center;
        }
        
        .tile-duration:before {
            content: "👥";
            margin-right: 8px;
            font-size: 14px;
        }
        
        .tile-content {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .features-list {
            list-style-type: none;
            margin-bottom: 25px;
            flex-grow: 1;
        }
        
        .features-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            padding-left: 32px;
            font-size: 16px;
        }
        
        .tile-basic .features-list li:before {
            color: #00b294;
        }
        
        .tile-family .features-list li:before {
            color: #0078d7;
        }
        
        .tile-professional .features-list li:before {
            color: #8661c5;
        }
        
        .tile-deluxe .features-list li:before {
            color: #e3008c;
        }
        
        .tile-wedding .features-list li:before {
            color: #ff8c00;
        }
        
        .tile-commercial .features-list li:before {
            color: #107c10;
        }
        
        .features-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            font-weight: bold;
            font-size: 18px;
        }
        
        .tile-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .tile-price {
            font-size: 32px;
            font-weight: 600;
        }
        
        .tile-basic .tile-price {
            color: #00b294;
        }
        
        .tile-family .tile-price {
            color: #0078d7;
        }
        
        .tile-professional .tile-price {
            color: #8661c5;
        }
        
        .tile-deluxe .tile-price {
            color: #e3008c;
        }
        
        .tile-wedding .tile-price {
            color: #ff8c00;
        }
        
        .tile-commercial .tile-price {
            color: #107c10;
        }
        
        .book-btn {
            background-color: transparent;
            border: 2px solid;
            border-radius: 3px;
            padding: 8px 16px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .tile-basic .book-btn {
            color: #00b294;
            border-color: #00b294;
        }
        
        .tile-basic .book-btn:hover {
            background-color: #00b294;
            color: white;
        }
        
        .tile-family .book-btn {
            color: #0078d7;
            border-color: #0078d7;
        }
        
        .tile-family .book-btn:hover {
            background-color: #0078d7;
            color: white;
        }
        
        .tile-professional .book-btn {
            color: #8661c5;
            border-color: #8661c5;
        }
        
        .tile-professional .book-btn:hover {
            background-color: #8661c5;
            color: white;
        }
        
        .tile-deluxe .book-btn {
            color: #e3008c;
            border-color: #e3008c;
        }
        
        .tile-deluxe .book-btn:hover {
            background-color: #e3008c;
            color: white;
        }
        
        .tile-wedding .book-btn {
            color: #ff8c00;
            border-color: #ff8c00;
        }
        
        .tile-wedding .book-btn:hover {
            background-color: #ff8c00;
            color: white;
        }
        
        .tile-commercial .book-btn {
            color: #107c10;
            border-color: #107c10;
        }
        
        .tile-commercial .book-btn:hover {
            background-color: #107c10;
            color: white;
        }
        
        .price-note {
            font-size: 14px;
            color: #777;
            margin-top: 5px;
            display: block;
        }
        
        .badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #ff4081;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transform: rotate(15deg);
        }
        
        @media (max-width: 768px) {
            .metro-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Premium Catering Services Packages</h1>
            <p class="subtitle">Make your event unforgettable with our professional catering services tailored to your unique tastes and requirements.</p>
        </header>
        
        <div class="metro-grid">
            <div class="metro-tile tile-basic">
                <div class="tile-header">
                    <div class="tile-name">Basic Catering Package</div>
                    <div class="tile-duration">Up to 25 guests</div>
                </div>
                <div class="tile-content">
                    <ul class="features-list">
                        <li>3 appetizer selections</li>
                        <li>2 main course options</li>
                        <li>1 dessert option</li>
                        <li>Basic tableware included</li>
                        <li>Self-service setup</li>
                    </ul>
                    <div class="tile-footer">
                        <div>
                            <div class="tile-price">₱799/person</div>
                            <span class="price-note">Perfect for small gatherings</span>
                        </div>
                        <a href="basic_catering_package.php" class="book-btn">Book Now</a>
                    </div>
                </div>
            </div>
            
            <div class="metro-tile tile-family">
                <div class="tile-header">
                    <div class="tile-name">Family Feast Package</div>
                    <div class="tile-duration">Up to 50 guests</div>
                </div>
                <div class="tile-content">
                    <ul class="features-list">
                        <li>5 appetizer selections</li>
                        <li>3 main course options</li>
                        <li>2 side dishes</li>
                        <li>2 dessert options</li>
                        <li>Premium tableware included</li>
                        <li>1 service staff included</li>
                    </ul>
                    <div class="tile-footer">
                        <div>
                            <div class="tile-price">₱1,200/person</div>
                            <span class="price-note">Our most popular option</span>
                        </div>
                        <a href="family_feast_package.php" class="book-btn">Book Now</a>
                    </div>
                </div>
            </div>
            
            <div class="metro-tile tile-professional">
                <div class="tile-header">
                    <div class="tile-name">Wedding Reception Package</div>
                    <div class="tile-duration">50-150 guests</div>
                </div>
                <div class="tile-content">
                    <ul class="features-list">
                        <li>Elegant buffet setup</li>
                        <li>6 appetizer selections</li>
                        <li>4 main course options</li>
                        <li>Wedding cake service</li>
                        <li>Full waitstaff service</li>
                    </ul>
                    <div class="tile-footer">
                        <div>
                            <div class="tile-price">₱1,500/person</div>
                            <span class="price-note">Ideal for your special day</span>
                        </div>
                        <a href="wedding_reception_package.php" class="book-btn">Book Now</a>
                    </div>
                </div>
            </div>
            
            <div class="metro-tile tile-deluxe">
                <div class="tile-header">
                    <div class="tile-name">Deluxe Corporate Package</div>
                    <div class="tile-duration">Up to 100 guests</div>
                    <div class="badge">Popular</div>
                </div>
                <div class="tile-content">
                    <ul class="features-list">
                        <li>Executive buffet service</li>
                        <li>Premium menu selections</li>
                        <li>Customized menu options</li>
                        <li>Professional serving staff</li>
                        <li>Elegant table settings</li>
                        <li>Beverage service included</li>
                    </ul>
                    <div class="tile-footer">
                        <div>
                            <div class="tile-price">₱1,800/person</div>
                            <span class="price-note">Our premium option</span>
                        </div>
                        <a href="deluxe_corporate_package.php" class="book-btn">Book Now</a>
                    </div>
                </div>
            </div>
            
            <div class="metro-tile tile-wedding">
                <div class="tile-header">
                    <div class="tile-name">Cocktail Reception</div>
                    <div class="tile-duration">Up to 75 guests</div>
                </div>
                <div class="tile-content">
                    <ul class="features-list">
                        <li>8 passed hors d'oeuvres</li>
                        <li>2 food stations</li>
                        <li>Signature cocktail service</li>
                        <li>Premium bar setup</li>
                        <li>Skilled bartenders included</li>
                        <li>Elegant cocktail tables</li>
                    </ul>
                    <div class="tile-footer">
                        <div>
                            <div class="tile-price">₱1,400/person</div>
                            <span class="price-note">For elegant social events</span>
                        </div>
                        <a href="cocktail_reception.php" class="book-btn">Book Now</a>
                    </div>
                </div>
            </div>
            
            <div class="metro-tile tile-commercial">
                <div class="tile-header">
                    <div class="tile-name">Premier Event Package</div>
                    <div class="tile-duration">150+ guests</div>
                </div>
                <div class="tile-content">
                    <ul class="features-list">
                        <li>Custom menu creation</li>
                        <li>Multi-course plated service</li>
                        <li>Dedicated event coordinator</li>
                        <li>Full-service staffing</li>
                        <li>Premium bar packages</li>
                        <li>Decor coordination available</li>
                    </ul>
                    <div class="tile-footer">
                        <div>
                            <div class="tile-price">₱2,500/person</div>
                            <span class="price-note">For luxury events</span>
                        </div>
                        <a href="premier_event_package.php" class="book-btn">Book Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>