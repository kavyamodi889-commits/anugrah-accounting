<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Fetch published feedback
$published_feedbacks = $conn->query("SELECT f.*, u.name as user_name 
    FROM feedback f 
    LEFT JOIN users u ON f.user_id = u.id 
    WHERE f.is_published = 1 
    ORDER BY f.created_at DESC 
    LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anugrah Accounting Service - Professional Tax & Accounting Solutions</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
   
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <div class="logo-icon">
                    <div class="logo-icon-bg"></div>
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-name">ANUGRAH</span>
                    <span class="brand-tagline">Accounting Service</span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                
                    <?php if(isUserLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle btn" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" style="background: linear-gradient(135deg, #27ae60, #229954); color: white; border-radius: 50px; padding: 10px 25px; margin-left: 10px;">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars(getUserName()); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i> My Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="user_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-outline-light" href="user_login.php" style="margin-left: 10px; border-radius: 50px; padding: 10px 25px;">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="hero-content">
                        <h1>Anugrah <span class="highlight">Accounting</span> Service</h1>
                        <p>Professional Tax & Accounting Solutions for Your Business Growth</p>
                        <div class="hero-buttons">
                            <a href="#services" class="btn btn-primary-custom">
                                <i class="fas fa-file-invoice"></i> Our Services
                            </a>
                            <a href="#contact" class="btn btn-outline-custom">
                                <i class="fas fa-envelope"></i> Contact Us
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 500 500'%3E%3Cg%3E%3Ccircle cx='250' cy='250' r='200' fill='%23FF8C42' opacity='0.2'/%3E%3Ccircle cx='250' cy='250' r='150' fill='%23FF8C42' opacity='0.3'/%3E%3Ccircle cx='250' cy='250' r='100' fill='%23FF8C42' opacity='0.4'/%3E%3Ctext x='250' y='270' font-size='100' text-anchor='middle' fill='white' font-family='Arial'%3E📊%3C/text%3E%3C/g%3E%3C/svg%3E" alt="Accounting" class="img-fluid" style="animation: float 3s ease-in-out infinite;">
                </div>
            </div>
        </div>
    </section>

   <!-- Services Section - REPLACE THIS ENTIRE SECTION IN YOUR FILE -->
<section class="services-section" id="services">
    <div class="container-fluid">
        <div class="section-title" data-aos="fade-up">
            <h2>Our Professional Services</h2>
            <p class="text-muted">Comprehensive accounting and tax solutions tailored for your business growth</p>
        </div>

        <!-- Service Categories Tabs -->
        <div class="service-tabs" data-aos="fade-up" data-aos-delay="100">
            <button class="service-tab active" data-category="all">
                <i class="fas fa-th"></i> All Services
            </button>
            <button class="service-tab" data-category="tax">
                <i class="fas fa-file-invoice-dollar"></i> Tax Services
            </button>
            <button class="service-tab" data-category="registration">
                <i class="fas fa-registered"></i> Registration
            </button>
            <button class="service-tab" data-category="compliance">
                <i class="fas fa-clipboard-check"></i> Compliance
            </button>
        </div>

        <div class="services-grid">
            <!-- Accounting Service -->
            <div class="modern-service-card" data-category="all compliance" data-aos="zoom-in" data-aos-delay="100">
                <div class="service-card-inner">
                    <div class="service-badge">Popular</div>
                    <div class="service-icon-modern">
                        <div class="icon-bg"></div>
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h4>Accounting Services</h4>
                    <p>Professional bookkeeping and financial management solutions for your business</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('accounting_services_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Income Tax Return -->
            <div class="modern-service-card" data-category="all tax" data-aos="zoom-in" data-aos-delay="150">
                <div class="service-card-inner">
                    <div class="service-badge recommended">Recommended</div>
                    <div class="service-icon-modern gradient-2">
                        <div class="icon-bg"></div>
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h4>Income Tax Return</h4>
                    <p>Expert ITR filing services with maximum tax savings for individuals and businesses</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('income_tax_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- GST Registration -->
            <div class="modern-service-card" data-category="all registration" data-aos="zoom-in" data-aos-delay="200">
                <div class="service-card-inner">
                    <div class="service-icon-modern gradient-3">
                        <div class="icon-bg"></div>
                        <i class="fas fa-registered"></i>
                    </div>
                    <h4>GST Registration</h4>
                    <p>Quick and hassle-free GST registration with complete documentation support</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('gst_registration_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- GST Returns -->
            <div class="modern-service-card" data-category="all tax compliance" data-aos="zoom-in" data-aos-delay="250">
                <div class="service-card-inner">
                    <div class="service-icon-modern gradient-4">
                        <div class="icon-bg"></div>
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h4>GST Returns</h4>
                    <p>Timely and accurate filing of all GST returns with compliance management</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('gst_returns_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- FSSAI Licence -->
            <div class="modern-service-card" data-category="all registration" data-aos="zoom-in" data-aos-delay="300">
                <div class="service-card-inner">
                    <div class="service-icon-modern gradient-5">
                        <div class="icon-bg"></div>
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h4>FSSAI Licence</h4>
                    <p>Food safety license registration and renewals for all food businesses</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('fssai_licence_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- MSME Registration -->
            <div class="modern-service-card" data-category="all registration" data-aos="zoom-in" data-aos-delay="350">
                <div class="service-card-inner">
                    <div class="service-icon-modern gradient-6">
                        <div class="icon-bg"></div>
                        <i class="fas fa-industry"></i>
                    </div>
                    <h4>MSME Registration</h4>
                    <p>Udyam registration for enterprises with government benefits and loan advantages</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('msme_registration_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- CMA Data -->
            <div class="modern-service-card" data-category="all compliance" data-aos="zoom-in" data-aos-delay="400">
                <div class="service-card-inner">
                    <div class="service-icon-modern gradient-7">
                        <div class="icon-bg"></div>
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>CMA Data</h4>
                    <p>Credit monitoring reports for loan applications and financial projections</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('cma_data_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tax Planning -->
            <div class="modern-service-card" data-category="all tax" data-aos="zoom-in" data-aos-delay="450">
                <div class="service-card-inner">
                    <div class="service-badge new">New</div>
                    <div class="service-icon-modern gradient-8">
                        <div class="icon-bg"></div>
                        <i class="fas fa-coins"></i>
                    </div>
                    <h4>Tax Planning</h4>
                    <p>Strategic planning to minimize tax liability and maximize business savings</p>
                    <div class="service-footer">
                        <a href="#" onclick="return checkAuthAndRedirect('tax_planning_form.php')" class="btn-modern-apply">
                            Apply Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="services-cta" data-aos="fade-up" data-aos-delay="500">
            <div class="cta-content">
                <h3>Need Help Choosing the Right Service?</h3>
                <p>Our experts are here to guide you. Get a free consultation today!</p>
                <div class="cta-buttons">
                    <a href="#contact" class="btn btn-cta-primary">
                        <i class="fas fa-phone-alt"></i> Schedule Consultation
                    </a>
                    <a href="https://wa.me/918000687342" class="btn btn-cta-secondary">
                        <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- About Section -->
    <section class="about-section" id="about">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2 style="color: var(--text-dark);">Why Choose Anugrah Accounting?</h2>
                <p class="text-muted">Your trusted partner for all accounting and tax needs</p>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="about-content">
                        <div class="about-feature">
                            <div class="feature-number">01</div>
                            <div>
                                <h4>Expert Team</h4>
                                <p>Experienced professionals with in-depth knowledge of tax laws and accounting standards</p>
                            </div>
                        </div>
                        <div class="about-feature">
                            <div class="feature-number">02</div>
                            <div>
                                <h4>Timely Service</h4>
                                <p>We ensure all your filings and registrations are completed before deadlines</p>
                            </div>
                        </div>
                        <div class="about-feature">
                            <div class="feature-number">03</div>
                            <div>
                                <h4>Affordable Pricing</h4>
                                <p>Transparent pricing with no hidden charges - quality service at competitive rates</p>
                            </div>
                        </div>
                        <div class="about-feature">
                            <div class="feature-number">04</div>
                            <div>
                                <h4>24/7 Support</h4>
                                <p>Always available to answer your queries via phone, email, or WhatsApp</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="about-image-container">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 600'%3E%3Cdefs%3E%3ClinearGradient id='grad1' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23FF8C42;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23e67e3c;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Ccircle cx='300' cy='300' r='250' fill='url(%23grad1)' opacity='0.2'/%3E%3Ccircle cx='300' cy='300' r='200' fill='url(%23grad1)' opacity='0.3'/%3E%3Ctext x='300' y='320' font-size='120' text-anchor='middle' fill='%23FF8C42' font-family='Arial' font-weight='bold'%3E📊%3C/text%3E%3Ctext x='300' y='380' font-size='30' text-anchor='middle' fill='%234a5568' font-family='Arial'%3EAccounting%3C/text%3E%3Ctext x='300' y='410' font-size='30' text-anchor='middle' fill='%234a5568' font-family='Arial'%3EExperts%3C/text%3E%3C/svg%3E" alt="About Us" class="img-fluid about-image">
                        <div class="experience-badge" data-aos="zoom-in" data-aos-delay="500">
                            <div class="badge-content">
                                <h3>5+</h3>
                                <p>Years Experience</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

   <!-- Testimonials Section - REPLACE THIS ENTIRE SECTION -->
<section class="testimonials-section">
    <div class="container">
        <div class="section-title" data-aos="fade-up">
            <h2 style="color: white;">What Our Clients Say</h2>
            <p style="color: #ecf0f1;">Real experiences from satisfied clients</p>
        </div>
        
        <?php if($published_feedbacks && $published_feedbacks->num_rows > 0): ?>
            <?php 
            $feedbacks_array = [];
            while($feedback = $published_feedbacks->fetch_assoc()) {
                $feedbacks_array[] = $feedback;
            }
            $total_feedbacks = count($feedbacks_array);
            ?>
            
            <!-- Carousel for more than 3 testimonials -->
            <?php if($total_feedbacks > 3): ?>
                <div id="testimonialsCarousel" class="carousel slide" data-bs-ride="carousel" data-aos="fade-up">
                    <div class="carousel-indicators">
                        <?php 
                        $slides = ceil($total_feedbacks / 3);
                        for($i = 0; $i < $slides; $i++): 
                        ?>
                            <button type="button" data-bs-target="#testimonialsCarousel" data-bs-slide-to="<?php echo $i; ?>" 
                                <?php echo $i === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                                aria-label="Slide <?php echo $i + 1; ?>">
                            </button>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="carousel-inner">
                        <?php 
                        $chunks = array_chunk($feedbacks_array, 3);
                        foreach($chunks as $index => $chunk): 
                        ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <div class="row">
                                    <?php foreach($chunk as $feedback): ?>
                                        <div class="col-lg-4 col-md-6 mb-4">
                                            <div class="testimonial-card">
                                                <?php if($feedback['admin_response']): ?>
                                                    <span class="service-badge">Verified</span>
                                                <?php endif; ?>
                                                
                                                <div class="stars">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <?php if($i <= $feedback['rating']): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                                
                                                <p class="testimonial-text">"<?php echo htmlspecialchars($feedback['feedback_text']); ?>"</p>
                                                
                                                <?php if($feedback['admin_response']): ?>
                                                    <div class="admin-response-box">
                                                        <small class="response-label">
                                                            <i class="fas fa-reply"></i> Our Response:
                                                        </small>
                                                        <p class="response-text">
                                                            <?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="client-info">
                                                    <div class="client-avatar">
                                                        <?php echo strtoupper(substr($feedback['user_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h5><?php echo htmlspecialchars($feedback['user_name']); ?></h5>
                                                        <p><?php echo htmlspecialchars($feedback['service_used']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button class="carousel-control-prev" type="button" data-bs-target="#testimonialsCarousel" data-bs-slide="prev">
                        <span class="carousel-control-icon">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#testimonialsCarousel" data-bs-slide="next">
                        <span class="carousel-control-icon">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            
            <!-- Grid for 3 or fewer testimonials -->
            <?php else: ?>
                <div class="row">
                    <?php 
                    $delay = 100;
                    foreach($feedbacks_array as $feedback): 
                    ?>
                        <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                            <div class="testimonial-card">
                                <?php if($feedback['admin_response']): ?>
                                    <span class="service-badge">Verified</span>
                                <?php endif; ?>
                                
                                <div class="stars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <?php if($i <= $feedback['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                
                                <p class="testimonial-text">"<?php echo htmlspecialchars($feedback['feedback_text']); ?>"</p>
                                
                                <?php if($feedback['admin_response']): ?>
                                    <div class="admin-response-box">
                                        <small class="response-label">
                                            <i class="fas fa-reply"></i> Our Response:
                                        </small>
                                        <p class="response-text">
                                            <?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="client-info">
                                    <div class="client-avatar">
                                        <?php echo strtoupper(substr($feedback['user_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h5><?php echo htmlspecialchars($feedback['user_name']); ?></h5>
                                        <p><?php echo htmlspecialchars($feedback['service_used']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                    $delay += 100;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if($total_feedbacks > 6): ?>
                <div class="text-center mt-5" data-aos="fade-up">
                    <a href="all_testimonials.php" class="btn btn-cta-primary">
                        <i class="fas fa-comments"></i> View All Testimonials (<?php echo $total_feedbacks; ?>)
                    </a>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-feedback-message" data-aos="fade-up">
                <i class="fas fa-comments"></i>
                <h4>No testimonials yet</h4>
                <p>Be the first to share your experience with our services!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ENHANCED FEEDBACK SECTION WITH PROPER GOOGLE REVIEWS -->
<div class="container">
    <div class="feedback-section-title">
        <div class="title-icon">
            <i class="fas fa-comment-dots"></i>
        </div>
        <h2>Share Your Experience</h2>
        <p>We value your feedback! Help us serve you better</p>
    </div>

    <!-- Two Options Side by Side -->
    <div class="row justify-content-center mb-5" data-aos="fade-up">
        <!-- Option 1: Google Review -->
        <div class="col-lg-6 mb-4">
            <div class="review-option-card google-card">
                <div class="option-icon google-icon">
                    <i class="fab fa-google"></i>
                </div>
                <h3>Leave a Google Review</h3>
                <p>Share your experience publicly on Google and help others discover our services</p>
                <ul class="benefits-list">
                    <li><i class="fas fa-check"></i> Appears on Google Search</li>
                    <li><i class="fas fa-check"></i> Visible to everyone</li>
                    <li><i class="fas fa-check"></i> Boosts our credibility</li>
                </ul>
                <!-- REPLACE WITH YOUR ACTUAL GOOGLE REVIEW LINK -->
                <a href="https://share.google/SsCAKg4v5APGNap98" 
                   target="_blank" 
                   class="btn-review-option btn-google"
                   onclick="trackGoogleReview()">
                    <i class="fab fa-google"></i> Write Google Review
                </a>
            </div>
        </div>

        <!-- Option 2: Website Feedback -->
        <div class="col-lg-6 mb-4">
            <div class="review-option-card website-card">
                <div class="option-icon website-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>Submit Website Feedback</h3>
                <p>Share your private feedback with us directly for internal improvements</p>
                <ul class="benefits-list">
                    <li><i class="fas fa-check"></i> Private submission</li>
                    <li><i class="fas fa-check"></i> Direct to management</li>
                    <li><i class="fas fa-check"></i> Quick response</li>
                </ul>
                <a href="#websiteFeedbackForm" 
                   class="btn-review-option btn-website"
                   onclick="scrollToFeedbackForm()">
                    <i class="fas fa-edit"></i> Fill Feedback Form
                </a>
            </div>
        </div>
    </div>

    <!-- Divider -->
    <div class="feedback-divider" data-aos="fade-up">
        <span>Website Feedback Form</span>
    </div>

    <!-- Website Feedback Form -->
    <div class="row justify-content-center" id="websiteFeedbackForm">
        <div class="col-lg-8">
            <div class="enhanced-feedback-card" data-aos="fade-up">
                <div class="form-header">
                    <h3>Submit Your Feedback</h3>
                    <p>This feedback will be reviewed by our team internally</p>
                </div>

                <form id="feedbackForm">
                    <!-- Name Field -->
                    <div class="form-group-enhanced">
                        <label class="form-label-enhanced">
                            <i class="fas fa-user"></i> Your Name
                        </label>
                        <div class="input-with-icon">
                            <input type="text" name="name" class="form-control-enhanced" 
                                   placeholder="Enter your full name" required>
                            <i class="fas fa-check-circle input-icon"></i>
                        </div>
                    </div>

                    <!-- Email Field -->
                    <div class="form-group-enhanced">
                        <label class="form-label-enhanced">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <div class="input-with-icon">
                            <input type="email" name="email" class="form-control-enhanced" 
                                   placeholder="your.email@example.com" required>
                            <i class="fas fa-check-circle input-icon"></i>
                        </div>
                    </div>

                    <!-- Service Selection -->
                    <div class="form-group-enhanced">
                        <label class="form-label-enhanced">
                            <i class="fas fa-briefcase"></i> Service Used
                        </label>
                        <select name="service_used" class="form-control-enhanced" required>
                            <option value="">Select the service you used</option>
                            <option value="Accounting">📊 Accounting Services</option>
                            <option value="Income Tax Return">💰 Income Tax Return</option>
                            <option value="GST Registration">📝 GST Registration</option>
                            <option value="GST Returns">📋 GST Returns</option>
                            <option value="FSSAI Licence">🍽️ FSSAI Licence</option>
                            <option value="MSME Registration">🏭 MSME Registration</option>
                            <option value="CMA Data">📈 CMA Data</option>
                            <option value="Tax Planning">💡 Tax Planning</option>
                        </select>
                    </div>

                    <!-- Rating Section -->
                    <div class="form-group-enhanced">
                        <label class="form-label-enhanced">
                            <i class="fas fa-star"></i> Rate Your Experience
                        </label>
                        <div class="rating-container">
                            <div class="rating-stars" id="ratingStars">
                                <i class="far fa-star" data-rating="1"></i>
                                <i class="far fa-star" data-rating="2"></i>
                                <i class="far fa-star" data-rating="3"></i>
                                <i class="far fa-star" data-rating="4"></i>
                                <i class="far fa-star" data-rating="5"></i>
                            </div>
                            <div class="rating-text" id="ratingText">Click a star to rate</div>
                            <input type="hidden" name="rating" id="ratingValue" required>
                        </div>
                    </div>

                    <!-- Feedback Text -->
                    <div class="form-group-enhanced">
                        <label class="form-label-enhanced">
                            <i class="fas fa-comment-alt"></i> Your Feedback
                        </label>
                        <textarea name="feedback_text" id="feedbackText" 
                                  class="form-control-enhanced" 
                                  placeholder="Share your detailed experience with us..." 
                                  required maxlength="500"></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span>/500 characters
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit-enhanced">
                        <span>
                            <i class="fas fa-paper-plane"></i>
                            Submit Your Feedback
                        </span>
                    </button>
                </form>

                <div id="feedbackMessage"></div>
            </div>
        </div>
    </div>

    <!-- Show Both Reviews Section -->
    <div class="review-display-section mt-5" data-aos="fade-up">
        <div class="section-title text-center">
            <h3>What Our Clients Say</h3>
            <p class="text-muted">Reviews from Google and our website</p>
        </div>
        
        <!-- Tabs for switching between Google and Website reviews -->
        <ul class="nav nav-pills justify-content-center mb-4" id="reviewTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active review-tab" id="google-reviews-tab" 
                        data-bs-toggle="pill" data-bs-target="#google-reviews" 
                        type="button" role="tab">
                    <i class="fab fa-google"></i> Google Reviews
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link review-tab" id="website-reviews-tab" 
                        data-bs-toggle="pill" data-bs-target="#website-reviews" 
                        type="button" role="tab">
                    <i class="fas fa-comments"></i> Website Feedback
                </button>
            </li>
        </ul>

        <div class="tab-content" id="reviewTabContent">
            <!-- Google Reviews Tab -->
            <div class="tab-pane fade show active" id="google-reviews" role="tabpanel">
                <div class="google-reviews-embed">
                    <!-- Elfsight Google Reviews Widget (Recommended) -->
                    <div class="text-center p-5">
                        <i class="fab fa-google" style="font-size: 4rem; color: #4285f4; margin-bottom: 20px;"></i>
                        <h4>See Our Google Reviews</h4>
                        <p>Check out what our clients are saying on Google</p>
                        <a href="https://www.google.com/search?q=Anugrah+Accounting+Service+Karamsad" 
                           target="_blank" 
                           class="btn btn-primary-custom mt-3">
                            <i class="fab fa-google"></i> View on Google
                        </a>
                    </div>
                </div>
            </div>

            <!-- Website Reviews Tab -->
            <div class="tab-pane fade" id="website-reviews" role="tabpanel">
                <!-- Your existing testimonials carousel here -->
                <?php if($published_feedbacks && $published_feedbacks->num_rows > 0): ?>
                    <!-- Your existing testimonials code -->
                <?php else: ?>
                    <div class="text-center p-5">
                        <i class="fas fa-comments" style="font-size: 4rem; color: var(--primary-orange); margin-bottom: 20px;"></i>
                        <h4>No website feedback yet</h4>
                        <p>Be the first to share your experience!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</section>

           

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2 style="color: var(--text-dark);">Frequently Asked Questions</h2>
                <p class="text-muted">Find answers to common questions about our services</p>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="100">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    <i class="fas fa-question-circle me-2"></i> What documents are required for ITR filing?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    For ITR filing, you need: PAN Card, Aadhaar Card, Form 16 (if salaried), Bank statements, Investment proofs (80C, 80D), and details of other income sources if any.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="200">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    <i class="fas fa-question-circle me-2"></i> How long does GST registration take?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    GST registration typically takes 3-7 working days once all required documents are submitted. We ensure quick processing and keep you updated throughout.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="300">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    <i class="fas fa-question-circle me-2"></i> What are your service charges?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Our charges vary based on the service and complexity. We offer competitive rates with transparent pricing. Contact us for detailed quotes for your specific requirements.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="400">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    <i class="fas fa-question-circle me-2"></i> Do you provide support after service completion?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! We provide ongoing support for all our services. You can reach us via phone, WhatsApp, or email anytime for queries or assistance.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="500">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    <i class="fas fa-question-circle me-2"></i> Can you handle GST returns filing monthly?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Absolutely! We offer monthly GST return filing services with reminders before due dates. Our team ensures accurate filing and compliance with all GST regulations.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Location Section -->
    <section class="location-section" id="location">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2 style="color: white;">Visit Our Office</h2>
                <p style="color: #ecf0f1;">We're located in the heart of Karamsad, easy to reach</p>
            </div>
            <div class="row">
                <div class="col-lg-4" data-aos="fade-right">
                    <div class="location-info-card">
                        <div class="location-icon-large">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4>Our Address</h4>
                        <p>Opp. Tirupati Petroleum<br>Anand Sojitra Road, Karamsad<br>Gujarat - 388325</p>
                        <hr>
                        <h4>Office Hours</h4>
                        <div class="office-hours">
                            <div class="hour-row">
                                <span>Monday - Saturday:</span>
                                <span>9:00 AM - 7:00 PM</span>
                            </div>
                            <div class="hour-row">
                                <span>Sunday:</span>
                                <span>By Appointment</span>
                            </div>
                        </div>
                        <hr>
                        <a href="https://wa.me/918000687342" class="btn btn-whatsapp w-100 mt-3">
                            <i class="fab fa-whatsapp"></i> WhatsApp Us
                        </a>
                    </div>
                </div>
                <div class="col-lg-8" data-aos="fade-left">
                    <div class="map-container">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3681.2856574447624!2d72.90173631495837!3d22.64736998512641!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x395e4e4e4e4e4e4f%3A0x4e4e4e4e4e4e4e4e!2sKaramsad%2C%20Gujarat!5e0!3m2!1sen!2sin!4v1234567890123!5m2!1sen!2sin" 
                            width="100%" 
                            height="450" 
                            style="border:0; border-radius: 20px;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                        <div class="map-overlay">
                            <a href="https://maps.google.com/?q=Karamsad,Gujarat" target="_blank" class="btn btn-primary-custom">
                                <i class="fas fa-directions"></i> Get Directions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2 style="color: white;">Get In Touch</h2>
                <p style="color: #ecf0f1;">We're here to help with all your accounting needs</p>
            </div>
            <div class="row">
                <div class="col-lg-4" data-aos="fade-right">
                    <div class="contact-info-card text-center">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5>Address</h5>
                        <p>Opp. Tirupati Petroleum, Anand Sojitra Road, Karamsad (GU) 388325</p>
                    </div>
                    
                    <div class="contact-info-card text-center">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h5>Phone</h5>
                        <p>Mayur Patel: 80006 87342</p>
                    </div>
                    
                    <div class="contact-info-card text-center">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5>Email</h5>
                        <p>anugrah0369@gmail.com</p>
                    </div>
                </div>
                
             <!-- Replace the Contact Form section in anugrah_home.php with this: -->

<div class="contact-form">
    <h3>Send Us a Message</h3>
    <form id="contactForm">
        <div class="row">
            <div class="col-md-6 mb-3">
                <input type="text" name="name" class="form-control" placeholder="Your Name" required>
            </div>
            <div class="col-md-6 mb-3">
                <input type="email" name="email" class="form-control" placeholder="Your Email" required>
            </div>
        </div>
        <div class="mb-3">
            <input type="text" name="phone" class="form-control" placeholder="Phone Number" pattern="[0-9]{10}" title="Please enter 10 digit phone number" required>
        </div>
        <div class="mb-3">
            <select name="service_interest" class="form-control" required>
                <option value="">Select Service Interest</option>
                <option value="Accounting">Accounting</option>
                <option value="Income Tax Return">Income Tax Return</option>
                <option value="GST Registration">GST Registration</option>
                <option value="GST Returns">GST Returns</option>
                <option value="FSSAI Licence">FSSAI Licence</option>
                <option value="MSME Registration">MSME Registration</option>
                <option value="CMA Data">CMA Data</option>
                <option value="Tax Planning">Tax Planning</option>
            </select>
        </div>
        <div class="mb-3">
            <textarea name="message" class="form-control" rows="5" placeholder="Your Message" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary-custom w-100">
            <i class="fas fa-paper-plane"></i> Send Message
        </button>
    </form>
    <div id="formFeedback" class="mt-3"></div>
</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p>&copy; 2024 Anugrah Accounting Service. All Rights Reserved.</p>
            <p>Designed with <i class="fas fa-heart" style="color: var(--primary-orange);"></i> for Your Business</p>
        </div>
    </footer>

    <!-- Floating WhatsApp Button -->
    <a href="https://wa.me/918000687342" target="_blank" class="whatsapp-float">
        <i class="fab fa-whatsapp"></i>
    </a>

   <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
       // Initialize AOS
AOS.init({
    duration: 1000,
    once: true,
    offset: 100
});

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ===== FIXED RATING FUNCTIONALITY =====
document.addEventListener('DOMContentLoaded', function() {
    const ratingStars = document.querySelectorAll('#ratingStars i');
    const ratingValue = document.getElementById('ratingValue');
    
    // Click event for rating
    ratingStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            ratingValue.value = rating;
            
            // Update all stars
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        });
        
        // Hover effect
        star.addEventListener('mouseenter', function() {
            const rating = this.getAttribute('data-rating');
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
    });
    
    // Mouse leave - restore selected rating
    document.querySelector('#ratingStars').addEventListener('mouseleave', function() {
        const currentRating = ratingValue.value;
        ratingStars.forEach((s, index) => {
            if (index < currentRating) {
                s.classList.remove('far');
                s.classList.add('fas', 'active');
            } else {
                s.classList.remove('fas', 'active');
                s.classList.add('far');
            }
        });
    });
});

// Contact form submission with AJAX
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const feedback = document.getElementById('formFeedback');
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Disable submit button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    // Get form data
    const formData = new FormData();
    formData.append('name', this.querySelector('input[placeholder="Your Name"]').value);
    formData.append('email', this.querySelector('input[placeholder="Your Email"]').value);
    formData.append('phone', this.querySelector('input[placeholder="Phone Number"]').value);
    formData.append('service_interest', this.querySelector('select').value);
    formData.append('message', this.querySelector('textarea').value);
    
    // Send AJAX request
    fetch('submit_contact.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            feedback.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
            this.reset();
        } else {
            feedback.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
        }
        
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        
        // Clear message after 5 seconds
        setTimeout(() => {
            feedback.innerHTML = '';
        }, 5000);
    })
    .catch(error => {
        feedback.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.</div>';
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        console.error('Error:', error);
    });
});

// Feedback form submission with AJAX
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const feedbackMessage = document.getElementById('feedbackMessage');
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    const ratingValue = document.getElementById('ratingValue');
    
    // Validate rating
    if(!ratingValue.value) {
        feedbackMessage.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Please select a rating!</div>';
        setTimeout(() => {
            feedbackMessage.innerHTML = '';
        }, 3000);
        return;
    }
    
    // Disable submit button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    // Get form data
    const formData = new FormData(this);
    
    // Send AJAX request
    fetch('submit_feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            feedbackMessage.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
            this.reset();
            
            // Reset star rating
            const ratingStars = document.querySelectorAll('#ratingStars i');
            ratingStars.forEach(s => {
                s.classList.remove('fas', 'active');
                s.classList.add('far');
            });
            ratingValue.value = '';
        } else {
            feedbackMessage.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
        }
        
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        
        // Clear message after 5 seconds
        setTimeout(() => {
            feedbackMessage.innerHTML = '';
        }, 5000);
    })
    .catch(error => {
        feedbackMessage.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.</div>';
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        console.error('Error:', error);
    });
});

// Service Category Filter
const serviceTabs = document.querySelectorAll('.service-tab');
const serviceCards = document.querySelectorAll('.modern-service-card');

serviceTabs.forEach(tab => {
    tab.addEventListener('click', function() {
        const category = this.getAttribute('data-category');
        
        // Update active tab
        serviceTabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        // Filter cards with animation
        serviceCards.forEach((card, index) => {
            const cardCategories = card.getAttribute('data-category').split(' ');
            
            if (category === 'all' || cardCategories.includes(category)) {
                card.style.display = 'block';
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                }, index * 50);
            } else {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    card.style.display = 'none';
                }, 300);
            }
        });
    });
});

// Initialize card animations
serviceCards.forEach(card => {
    card.style.transition = 'all 0.3s ease';
});

// Scroll reveal animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Apply to elements
document.querySelectorAll('.modern-service-card, .feature-card, .testimonial-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'all 0.6s ease-out';
    observer.observe(el);
});

// Counter animation for statistics
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 30);
}

// Trigger counter animation when about section is visible
const aboutSection = document.querySelector('.about-section');
if (aboutSection) {
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const experienceBadge = document.querySelector('.experience-badge h3');
                if (experienceBadge && !experienceBadge.classList.contains('counted')) {
                    experienceBadge.classList.add('counted');
                    animateCounter(experienceBadge, 5);
                }
                counterObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    counterObserver.observe(aboutSection);
}

// Parallax effect for service cards
document.addEventListener('mousemove', (e) => {
    const cards = document.querySelectorAll('.modern-service-card:hover .service-card-inner');
    cards.forEach(card => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        const rotateX = (y - centerY) / 20;
        const rotateY = (centerX - x) / 20;
        
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-15px)`;
    });
});

document.addEventListener('mouseleave', () => {
    const cards = document.querySelectorAll('.service-card-inner');
    cards.forEach(card => {
        card.style.transform = '';
    });
});

// Float animation
const style = document.createElement('style');
style.textContent = `
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);

document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.querySelector('#testimonialsCarousel');
    if (carousel) {
        const bsCarousel = new bootstrap.Carousel(carousel, {
            interval: 5000,
            wrap: true,
            touch: true
        });
    }
});

// Enhanced Rating functionality with messages
const ratingMessages = [
    '',
    '⭐ Poor - We can do better',
    '⭐⭐ Fair - Needs improvement',
    '⭐⭐⭐ Good - Satisfactory service',
    '⭐⭐⭐⭐ Very Good - Great experience',
    '⭐⭐⭐⭐⭐ Excellent - Outstanding service!'
];

document.addEventListener('DOMContentLoaded', function() {
    const ratingStars = document.querySelectorAll('#ratingStars i');
    const ratingValue = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');

    ratingStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            ratingValue.value = rating;
            ratingText.textContent = ratingMessages[rating];
            
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });

        star.addEventListener('mouseenter', function() {
            const rating = this.getAttribute('data-rating');
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
    });

    document.querySelector('#ratingStars').addEventListener('mouseleave', function() {
        const currentRating = ratingValue.value;
        ratingStars.forEach((s, index) => {
            if (index < currentRating) {
                s.classList.remove('far');
                s.classList.add('fas');
            } else {
                s.classList.remove('fas');
                s.classList.add('far');
            }
        });
    });

    // Character counter
    const feedbackText = document.getElementById('feedbackText');
    const charCount = document.getElementById('charCount');

    if (feedbackText && charCount) {
        feedbackText.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }
});

// Authentication check function
function checkAuthAndRedirect(formUrl) {
    const isLoggedIn = <?php echo isUserLoggedIn() ? 'true' : 'false'; ?>;
    
    if (!isLoggedIn) {
        // Create and show custom modal
        const modalHtml = `
            <div class="modal fade" id="authModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                        <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-orange), #e67e3c); color: white; border: none;">
                            <h5 class="modal-title"><i class="fas fa-lock"></i> Login Required</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center" style="padding: 40px;">
                            <i class="fas fa-user-lock" style="font-size: 4rem; color: var(--primary-orange); margin-bottom: 20px;"></i>
                            <h4 style="color: var(--text-dark); margin-bottom: 15px;">Please Login to Continue</h4>
                            <p style="color: #7f8c8d; margin-bottom: 30px;">You need to be logged in to access our service application forms</p>
                            <a href="user_login.php?redirect=${encodeURIComponent(formUrl)}" class="btn btn-primary-custom" style="padding: 15px 40px; border-radius: 50px; text-decoration: none;">
                                <i class="fas fa-sign-in-alt"></i> Login Now
                            </a>
                            <div style="margin-top: 20px;">
                                <small style="color: #95a5a6;">Don't have an account? <a href="user_login.php?redirect=${encodeURIComponent(formUrl)}" style="color: var(--primary-orange); font-weight: 600;">Register here</a></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('authModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('authModal'));
        modal.show();
        
        return false;
    } else {
        window.location.href = formUrl;
        return true;
    }
}
function trackGoogleReview() {
    // Optional: Track in Google Analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'click', {
            'event_category': 'Reviews',
            'event_label': 'Google Review Click'
        });
    }
    
    // Show thank you message
    setTimeout(() => {
        alert('Thank you for choosing to review us on Google! Your feedback means a lot.');
    }, 100);
}

// Smooth scroll to feedback form
function scrollToFeedbackForm() {
    const form = document.getElementById('websiteFeedbackForm');
    if (form) {
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

    </script>
</body>
</html>