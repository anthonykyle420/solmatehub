<?php
/**
 * Template Name: Homepage
 * Description: Main homepage for SolmateHub
 */

// No header/footer from theme
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Solmatehub - Discover Connections</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <script>
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              colors: {
                primary: "#EAE8F0",
                "background-light": "#F8F7FA",
                "background-dark": "#0A080C",
                "card-light": "#FFFFFF",
                "card-dark": "#16131A",
                "text-light": "#1A161F",
                "text-dark": "#EAE8F0",
                "muted-light": "#6B6772",
                "muted-dark": "#948EA3",
                "border-light": "#EAE8F0",
                "border-dark": "#2A2631",
                "gold": "#FFD700",
              },
              fontFamily: {
                display: ["Poppins", "sans-serif"],
                title: ["Playfair Display", "serif"],
              },
              borderRadius: {
                DEFAULT: "12px",
              },
            },
          },
        };
    </script>
    
    <style>
        .material-symbols-outlined {
          font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .verified-badge {
          color: #38bdf8;
          filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.7));
        }
        .crown-icon {
          font-variation-settings: 'FILL' 1, 'wght' 300, 'GRAD' 0, 'opsz' 24;
          color: #FFD700;
        }
        .vip-badge {
          font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 20;
          color: #9333ea;
          filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.7));
        }
        .logo-text {
          font-family: 'Playfair Display', serif;
          font-weight: 700;
          font-size: 1.875rem;
          background: linear-gradient(135deg, #EAE8F0 0%, #948EA3 100%);
          -webkit-background-clip: text;
          -webkit-text-fill-color: transparent;
          background-clip: text;
          cursor: pointer;
          transition: all 0.3s ease;
        }
        .logo-text:hover {
          transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-text-light dark:text-text-dark antialiased">

<div class="container mx-auto px-4 py-8">
    
    <!-- HEADER -->
    <header class="mb-8">
        <div class="flex items-center justify-between mb-6">
            <!-- Logo (clickable) -->
            <a href="<?php echo home_url(); ?>" class="logo-text">
                Solmatehub
            </a>
            
            <!-- Login/Register -->
            <div class="flex items-center gap-3">
                <a class="text-sm font-semibold text-primary/80 hover:text-primary transition-colors" href="#">Login</a>
                <a class="text-sm font-semibold bg-primary text-background-dark rounded-full px-4 py-2 hover:bg-primary/90 transition-colors" href="#">Register</a>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <!-- Category Dropdown -->
                <div class="relative">
                    <select class="w-full appearance-none bg-card-dark border border-border-dark rounded-full pl-5 pr-10 py-3 text-sm text-text-dark font-medium focus:outline-none focus:ring-2 focus:ring-primary/50 cursor-pointer">
                        <option>Category</option>
                        <option>Male</option>
                        <option>Female</option>
                        <option>Shemale</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-muted-dark pointer-events-none">expand_more</span>
                </div>
                
                <!-- Location Search -->
                <div class="relative">
                    <input class="w-full bg-card-dark border border-border-dark rounded-full pl-5 pr-4 py-3 text-sm text-text-dark font-medium focus:outline-none focus:ring-2 focus:ring-primary/50 placeholder:text-muted-dark" placeholder="City Search" type="text"/>
                </div>
            </div>
            
            <!-- Search Button + Filter -->
            <div class="flex items-center gap-2">
                <button class="flex-grow flex items-center justify-center bg-primary text-background-dark hover:bg-primary/90 rounded-full px-6 py-3 transition-colors font-semibold">
                    <span class="material-symbols-outlined mr-2">search</span>
                    <span>Search</span>
                </button>
                <button class="flex-shrink-0 flex items-center justify-center gap-1 text-sm font-medium text-muted-dark hover:text-primary transition-colors py-3 px-3 border border-border-dark rounded-full">
                    <span class="material-symbols-outlined text-xl">tune</span>
                </button>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        
        <!-- FEATURED PROFILES SECTION -->
        <section class="mb-12">
            <h2 class="text-2xl font-title text-text-dark mb-4">Featured Profiles</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                
                <?php
                // Query for FEATURED and APPROVED listings only
                $featured_args = array(
                    'post_type' => 'listing',
                    'posts_per_page' => 5,
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key' => 'is_featured',
                            'value' => '1',
                            'compare' => '='
                        ),
                        array(
                            'key' => 'listing_status',
                            'value' => 'approved',
                            'compare' => '='
                        )
                    )
                );
                $featured_query = new WP_Query($featured_args);
                
                if ($featured_query->have_posts()) :
                    while ($featured_query->have_posts()) : $featured_query->the_post();
                        $listing_id = get_the_ID();
                        
                        // ALL badges are now per-listing
                        $is_verified = get_post_meta($listing_id, 'listing_verified', true);
                        $is_vip = get_post_meta($listing_id, 'listing_vip', true);
                        
                        $location = wp_get_post_terms($listing_id, 'listing_location');
                        $location_name = !empty($location) ? $location[0]->name : 'Location';
                ?>
                
                <div class="group relative overflow-hidden rounded-lg shadow-lg aspect-[3/4]">
                    <!-- Crown Icon (Featured) -->
                    <div class="absolute top-2 right-2 z-10">
                        <span class="material-symbols-outlined text-3xl crown-icon" style="filter: drop-shadow(0 2px 3px rgba(0,0,0,0.7));">workspace_premium</span>
                    </div>
                    
                    <!-- Image -->
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('medium', array('class' => 'w-full h-full object-cover')); ?>
                    <?php else : ?>
                        <img class="w-full h-full object-cover" src="https://via.placeholder.com/300x400/16131A/EAE8F0?text=No+Image" alt="<?php the_title(); ?>"/>
                    <?php endif; ?>
                    
                    <!-- Gradient Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
                    
                    <!-- Info -->
                    <div class="absolute bottom-0 left-0 p-3 w-full text-white">
                        <div class="flex items-center gap-1">
                            <h3 class="font-semibold text-xs"><?php the_title(); ?></h3>
                            
                            <!-- Verified Badge (per-listing) -->
                            <?php if ($is_verified == '1') : ?>
                                <span class="material-symbols-outlined text-base verified-badge">verified</span>
                            <?php endif; ?>
                            
                            <!-- VIP Badge (per-listing) -->
                            <?php if ($is_vip == '1') : ?>
                                <span class="material-symbols-outlined text-base vip-badge">star</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[10px] opacity-90"><?php echo esc_html($location_name); ?></p>
                    </div>
                </div>
                
                <?php 
                    endwhile;
                    wp_reset_postdata();
                else :
                    // Show placeholder if no featured listings
                    for ($i = 0; $i < 4; $i++) :
                ?>
                <div class="group relative overflow-hidden rounded-lg shadow-lg aspect-[3/4] bg-card-dark flex items-center justify-center">
                    <p class="text-muted-dark text-sm">No Featured Listings Yet</p>
                </div>
                <?php 
                    endfor;
                endif; 
                ?>
                
            </div>
        </section>

        <!-- ALL PROFILES SECTION (VERIFIED FIRST, THEN BY DATE) -->
        <section>
            <h2 class="text-2xl font-title mb-4 text-text-dark">All Profiles</h2>
            <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                
                <?php
                // Query for APPROVED listings EXCLUDING FEATURED
                // SORTED BY: Verified first, then by date
                $all_args = array(
                    'post_type' => 'listing',
                    'posts_per_page' => 15,
                    'meta_query' => array(
                        'relation' => 'AND',
                        // Only approved listings
                        array(
                            'key' => 'listing_status',
                            'value' => 'approved',
                            'compare' => '='
                        ),
                        // Exclude featured listings
                        array(
                            'relation' => 'OR',
                            array(
                                'key' => 'is_featured',
                                'compare' => 'NOT EXISTS'
                            ),
                            array(
                                'key' => 'is_featured',
                                'value' => '0',
                                'compare' => '='
                            ),
                            array(
                                'key' => 'is_featured',
                                'value' => '',
                                'compare' => '='
                            )
                        ),
                        // Verified clause (for sorting priority)
                        'verified_clause' => array(
                            'key' => 'listing_verified',
                            'compare' => 'EXISTS'
                        )
                    ),
                    'orderby' => array(
                        'verified_clause' => 'DESC',  // Verified profiles first
                        'date' => 'DESC'               // Then newest first
                    )
                );
                $all_query = new WP_Query($all_args);
                
                if ($all_query->have_posts()) :
                    while ($all_query->have_posts()) : $all_query->the_post();
                        $listing_id = get_the_ID();
                        
                        // ALL badges are per-listing
                        $is_verified = get_post_meta($listing_id, 'listing_verified', true);
                        $is_vip = get_post_meta($listing_id, 'listing_vip', true);
                        
                        $location = wp_get_post_terms($listing_id, 'listing_location');
                        $location_name = !empty($location) ? $location[0]->name : 'Location';
                ?>
                
                <div class="group relative overflow-hidden rounded-lg shadow-lg aspect-[3/4]">
                    <!-- Image -->
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('medium', array('class' => 'w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-300')); ?>
                    <?php else : ?>
                        <img class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-300" src="https://via.placeholder.com/300x400/16131A/EAE8F0?text=No+Image" alt="<?php the_title(); ?>"/>
                    <?php endif; ?>
                    
                    <!-- Gradient Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
                    
                    <!-- Info -->
                    <div class="absolute bottom-0 left-0 p-2 w-full text-white">
                        <div class="flex items-center gap-1">
                            <h3 class="font-semibold text-xs"><?php the_title(); ?></h3>
                            
                            <!-- Verified Badge (per-listing) -->
                            <?php if ($is_verified == '1') : ?>
                                <span class="material-symbols-outlined text-base verified-badge">verified</span>
                            <?php endif; ?>
                            
                            <!-- VIP Badge (per-listing) -->
                            <?php if ($is_vip == '1') : ?>
                                <span class="material-symbols-outlined text-base vip-badge">star</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[10px] opacity-90"><?php echo esc_html($location_name); ?></p>
                    </div>
                </div>
                
                <?php 
                    endwhile;
                    wp_reset_postdata();
                else :
                ?>
                <div class="col-span-full text-center py-12">
                    <p class="text-muted-dark text-lg">No listings found. Create your first listing!</p>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Pagination -->
            <div class="flex justify-center mt-10">
                <a class="inline-flex items-center justify-center bg-card-dark border border-border-dark text-primary font-semibold rounded-full px-8 py-3 hover:bg-border-dark transition-colors" href="#">
                    Next Page
                </a>
            </div>
        </section>
        
    </main>
</div>

</body>
</html>