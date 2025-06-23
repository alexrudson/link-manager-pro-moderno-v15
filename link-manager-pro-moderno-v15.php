<?php
/**
 * Plugin Name:       Link Manager Pro (Modernizado e Completo)
 * Description:       Gerenciador de links completo com a interface original, mas usando tecnologia moderna e com todas as funcionalidades.
 * Version:           15.0.0
 * Author:            Alex Rudson & Assistente IA
 * Text Domain:       link-manager-pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LinkManagerProModerno {

    private static $instance;
    private $options;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option('lmp_settings', []);
        add_action('init', [ $this, 'register_cpt_and_taxonomy' ]);
        add_action('admin_menu', [ $this, 'add_admin_menu' ]);
        add_action('admin_init', [ $this, 'register_settings' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
        add_action('widgets_init', [ $this, 'register_widget' ]);
        add_action('wp_head', [ $this, 'output_custom_css' ]);
        add_shortcode('list_links', [ $this, 'render_shortcode' ]);
        add_filter('the_content', [ $this, 'filter_page_content' ], 25);
        add_action('admin_post_lmp_export_json', [ $this, 'handle_export' ]);
        add_action('wp_ajax_lmp_ajax_import', [ $this, 'handle_ajax_import' ]);
        add_action('add_meta_boxes', [$this, 'add_link_meta_boxes']);
        add_action('save_post_link', [$this, 'save_link_meta_data']);
    }

    public function register_cpt_and_taxonomy() {
        $labels = [ 'name' => 'Links', 'singular_name' => 'Link', 'menu_name' => 'Links', 'name_admin_bar' => 'Link', 'add_new' => 'Adicionar Novo', 'add_new_item' => 'Adicionar Novo Link', 'new_item' => 'Novo Link', 'edit_item' => 'Editar Link', 'view_item' => 'Ver Link', 'all_items' => 'Todos os Links', 'search_items' => 'Buscar Links', 'not_found' => 'Nenhum link encontrado.', 'not_found_in_trash' => 'Nenhum link na lixeira.' ];
        $args = [ 'labels' => $labels, 'public' => true, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true, 'rewrite' => ['slug' => 'links'], 'capability_type' => 'post', 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 20, 'supports' => ['title', 'thumbnail'], 'menu_icon' => 'dashicons-admin-links' ];
        register_post_type('link', $args);
        register_taxonomy('link_category', ['link'], ['hierarchical' => true, 'labels' => ['name' => 'Categorias de Links', 'singular_name' => 'Categoria'], 'show_ui' => true, 'show_admin_column' => true, 'query_var' => true, 'rewrite' => ['slug' => 'link-category']]);
    }

    public function add_link_meta_boxes() {
        add_meta_box('lmp_link_details', 'Detalhes do Link', [$this, 'render_meta_box_callback'], 'link', 'normal', 'high');
    }

    public function render_meta_box_callback($post) {
        wp_nonce_field('lmp_save_meta_data', 'lmp_nonce');
        echo '<p><label><strong>URL do Link:</strong></label><br><input type="url" name="lmp_link_url" value="'.esc_attr(get_post_meta($post->ID, 'link_url', true)).'" style="width:100%;"></p>';
        echo '<p><label><strong>Descrição:</strong></label><br><textarea name="lmp_link_description" rows="3" style="width:100%;">'.esc_textarea(get_post_meta($post->ID, 'link_description', true)).'</textarea></p>';
        echo '<p><label><strong>Abrir em nova aba?</strong></label><br><select name="lmp_link_target"><option value="_blank" '.selected(get_post_meta($post->ID, 'link_target', true), '_blank', false).'>Sim</option><option value="_self" '.selected(get_post_meta($post->ID, 'link_target', true), '_self', false).'>Não</option></select></p>';
    }

    public function save_link_meta_data($post_id) {
        if (!isset($_POST['lmp_nonce']) || !wp_verify_nonce($_POST['lmp_nonce'], 'lmp_save_meta_data') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) return;
        if (isset($_POST['lmp_link_url'])) update_post_meta($post_id, 'link_url', esc_url_raw($_POST['lmp_link_url']));
        if (isset($_POST['lmp_link_description'])) update_post_meta($post_id, 'link_description', sanitize_textarea_field($_POST['lmp_link_description']));
        if (isset($_POST['lmp_link_target'])) update_post_meta($post_id, 'link_target', sanitize_key($_POST['lmp_link_target']));
    }

    public function add_admin_menu() {
        add_submenu_page('edit.php?post_type=link', __('Opções e Aparência', 'link-manager-pro'), __('Opções e Aparência', 'link-manager-pro'), 'manage_options', 'link_manager_pro_options', [ $this, 'render_admin_page' ]);
    }

    public function register_settings() {
        register_setting('lmp_settings_group', 'lmp_settings', [ $this, 'sanitize_settings' ]);
    }
    
    public function enqueue_admin_assets($hook) {
        if ('link_page_link_manager_pro_options' !== $hook) return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('lmp-admin-scripts', plugin_dir_url(__FILE__) . 'assets/js/admin-scripts.js', ['jquery', 'wp-color-picker'], '2.0.0', true);
        wp_localize_script('lmp-admin-scripts', 'lmp_ajax', [ 'ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('lmp_import_nonce') ]);
    }

    public function sanitize_settings($input) {
        $sanitized_input = []; if (isset($input['page_id'])) $sanitized_input['page_id'] = absint($input['page_id']); if (isset($input['show_thumbnails'])) $sanitized_input['show_thumbnails'] = (bool) $input['show_thumbnails'];
        $css_fields = [ 'font_size_title', 'font_size_link', 'font_size_desc', 'color_link', 'color_link_hover', 'color_label' ];
        foreach ($css_fields as $field) if (isset($input[$field])) $sanitized_input[$field] = sanitize_text_field($input[$field]);
        if (isset($input['custom_css'])) $sanitized_input['custom_css'] = wp_strip_all_tags($input['custom_css']);
        return $sanitized_input;
    }

    public function render_admin_page() {
        ?>
        <div class="wrap" id="lmp-admin-wrap"><h1><?php _e('Opções do Gerenciador de Links', 'link-manager-pro'); ?></h1><form action="options.php" method="post"><?php settings_fields('lmp_settings_group'); $options = $this->options; ?><h2><?php _e('Configurações Gerais', 'link-manager-pro'); ?></h2><table class="form-table"><tr valign="top"><th scope="row"><?php _e('Página de Exibição dos Links', 'link-manager-pro'); ?></th><td><?php wp_dropdown_pages(['name' => 'lmp_settings[page_id]', 'selected' => $options['page_id'] ?? 0, 'show_option_none' => __('— Nenhuma —', 'link-manager-pro'), 'option_none_value' => '0']); ?><p class="description"><?php _e('Selecione uma página para exibir a lista de links. O widget usará esta página para criar os links das categorias.', 'link-manager-pro'); ?></p></td></tr><tr valign="top"><th scope="row"><?php _e('Exibir Imagens Destacadas', 'link-manager-pro'); ?></th><td><label><input type="checkbox" name="lmp_settings[show_thumbnails]" value="1" <?php checked(isset($options['show_thumbnails']) && $options['show_thumbnails'], 1); ?> /> <?php _e('Sim, exibir a imagem destacada ao lado do link (se houver).', 'link-manager-pro'); ?></label><p class="description"><?php _e('Você pode adicionar uma imagem destacada na tela de edição de cada link.', 'link-manager-pro'); ?></p></td></tr></table><h2><?php _e('Personalização Visual', 'link-manager-pro'); ?></h2><table class="form-table"><tr valign="top"><th scope="row"><?php _e('Tamanhos de Fonte', 'link-manager-pro'); ?></th><td><label><?php _e('Título da Categoria:', 'link-manager-pro'); ?></label><input type="text" name="lmp_settings[font_size_title]" value="<?php echo esc_attr($options['font_size_title'] ?? '1.2em'); ?>" placeholder="ex: 1.2em" /><br><label><?php _e('Link:', 'link-manager-pro'); ?></label><input type="text" name="lmp_settings[font_size_link]" value="<?php echo esc_attr($options['font_size_link'] ?? '1em'); ?>" placeholder="ex: 1em" /><br><label><?php _e('Descrição:', 'link-manager-pro'); ?></label><input type="text" name="lmp_settings[font_size_desc]" value="<?php echo esc_attr($options['font_size_desc'] ?? '0.9em'); ?>" placeholder="ex: 0.9em" /></td></tr><tr valign="top"><th scope="row"><?php _e('Cores', 'link-manager-pro'); ?></th><td><label><?php _e('Cor do Link:', 'link-manager-pro'); ?></label><input type="text" name="lmp_settings[color_link]" value="<?php echo esc_attr($options['color_link'] ?? '#0073aa'); ?>" class="lmp-color-picker" /><br><label><?php _e('Cor do Link (Hover):', 'link-manager-pro'); ?></label><input type="text" name="lmp_settings[color_link_hover]" value="<?php echo esc_attr($options['color_link_hover'] ?? '#00a0d2'); ?>" class="lmp-color-picker" /><br><label><?php _e('Cor do Título da Categoria:', 'link-manager-pro'); ?></label><input type="text" name="lmp_settings[color_label]" value="<?php echo esc_attr($options['color_label'] ?? '#333333'); ?>" class="lmp-color-picker" /></td></tr><tr valign="top"><th scope="row"><?php _e('CSS Personalizado', 'link-manager-pro'); ?></th><td><textarea name="lmp_settings[custom_css]" rows="10" class="large-text"><?php echo esc_textarea($options['custom_css'] ?? ''); ?></textarea><p class="description"><?php _e('Adicione suas próprias regras CSS aqui. Ex: <code>.lmp-unified-container { border: 1px solid #ccc; }</code>', 'link-manager-pro'); ?></p></td></tr></table><?php submit_button(); ?></form><hr><h2><?php _e('Importar / Exportar', 'link-manager-pro'); ?></h2><div style="display: flex; flex-wrap: wrap; gap: 20px;"><div style="flex: 1; min-width: 300px;"><h3><?php _e('Exportar Links', 'link-manager-pro'); ?></h3><p><?php _e('Clique para baixar um arquivo JSON com todos os links e categorias.', 'link-manager-pro'); ?></p><form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="lmp_export_json"><?php wp_nonce_field('lmp_export_nonce', 'lmp_export_nonce_field'); ?><?php submit_button(__('Exportar para JSON', 'link-manager-pro'), 'secondary'); ?></form></div><div style="flex: 1; min-width: 300px;"><h3><?php _e('Importar Links', 'link-manager-pro'); ?></h3><form id="lmp-import-form" method="post" enctype="multipart/form-data"><div id="lmp-import-feedback"></div><p><label for="lmp_import_file"><?php _e('Enviar arquivo JSON:', 'link-manager-pro'); ?></label><br><input type="file" name="lmp_import_file" id="lmp_import_file" accept=".json"></p><p><strong><?php _e('OU', 'link-manager-pro'); ?></strong></p><p><label for="lmp_import_text"><?php _e('Cole o conteúdo JSON:', 'link-manager-pro'); ?></label><br><textarea name="lmp_import_text" id="lmp_import_text" rows="8" class="large-text"></textarea></p><?php submit_button(__('Importar Links', 'link-manager-pro')); ?></form></div></div></div>
        <?php
    }

    public function output_custom_css() {
        $css = '';
        if (!empty($this->options['font_size_title'])) $css .= ".lmp-group-title { font-size: ".esc_attr($this->options['font_size_title'])."; }\n";
        if (!empty($this->options['font_size_link'])) $css .= ".lmp-item-list .lmp-item a, .widget_lmp_category_widget ul li a { font-size: ".esc_attr($this->options['font_size_link'])."; }\n";
        if (!empty($this->options['font_size_desc'])) $css .= ".lmp-link-description { font-size: ".esc_attr($this->options['font_size_desc'])."; }\n";
        if (!empty($this->options['color_label'])) $css .= ".lmp-group-title { color: ".esc_attr($this->options['color_label'])."; }\n";
        if (!empty($this->options['color_link'])) $css .= ".lmp-item-list .lmp-item a, .widget_lmp_category_widget ul li a { color: ".esc_attr($this->options['color_link'])."; }\n";
        if (!empty($this->options['color_link_hover'])) $css .= ".lmp-item-list .lmp-item a:hover, .widget_lmp_category_widget ul li a:hover { color: ".esc_attr($this->options['color_link_hover'])."; }\n";
        if (!empty($this->options['custom_css'])) $css .= "/* CSS Personalizado */\n".wp_strip_all_tags($this->options['custom_css'])."\n";
        if (!empty($css)) echo "<style id='link-manager-pro-styles'>".$css."</style>\n";
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(['category_id' => 0, 'show_descriptions' => 'true', 'show_thumbnails' => $this->options['show_thumbnails'] ?? false], $atts, 'list_links');
        
        $category_id = absint($atts['category_id']);
        if ( $category_id === 0 && isset($_GET['link_cat']) ) {
            $category_id = absint($_GET['link_cat']);
        }
        
        $show_descriptions = filter_var($atts['show_descriptions'], FILTER_VALIDATE_BOOLEAN);
        $show_thumbnails = filter_var($atts['show_thumbnails'], FILTER_VALIDATE_BOOLEAN);
        $output = '<div class="lmp-link-list lmp-unified-container">';

        if ($category_id > 0) {
            $category = get_term($category_id, 'link_category');
            if ($category && !is_wp_error($category)) $output .= $this->get_links_for_category($category, $show_descriptions, $show_thumbnails);
        } else {
            $categories = get_terms(['taxonomy' => 'link_category', 'hide_empty' => true, 'orderby' => 'name']);
            if (!empty($categories) && !is_wp_error($categories)) foreach ($categories as $category) $output .= $this->get_links_for_category($category, $show_descriptions, $show_thumbnails);
            else $output .= '<p>' . __('Nenhum link encontrado.', 'link-manager-pro') . '</p>';
        }
        $output .= '</div>';
        return $output;
    }

    private function get_links_for_category($category, $show_descriptions, $show_thumbnails) {
        $args = ['post_type' => 'link', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'tax_query' => [['taxonomy' => 'link_category', 'field' => 'term_id', 'terms' => $category->term_id]]];
        $links_query = new WP_Query($args);
        if (!$links_query->have_posts()) return '';
        $html = '<h3 class="lmp-group-title">' . esc_html($category->name) . '</h3>';
        $html .= '<ul class="lmp-item-list">';
        while ($links_query->have_posts()) {
            $links_query->the_post();
            $post_id = get_the_ID();
            $link_url = get_post_meta($post_id, 'link_url', true);
            $link_desc = get_post_meta($post_id, 'link_description', true);
            $link_target = get_post_meta($post_id, 'link_target', true) ?: '_blank';
            $thumbnail_html = '';
            if ($show_thumbnails && has_post_thumbnail($post_id)) { $thumbnail_html = '<div class="lmp-link-thumbnail">' . get_the_post_thumbnail($post_id, 'thumbnail') . '</div>'; }
            $html .= '<li class="lmp-item ' . ($thumbnail_html ? 'has-thumbnail' : '') . '">';
            if ($thumbnail_html) $html .= $thumbnail_html;
            $html .= '<div class="lmp-link-content"><a href="' . esc_url($link_url) . '" target="' . esc_attr($link_target) . '" rel="noopener">' . get_the_title() . '</a>';
            if ($show_descriptions && !empty($link_desc)) $html .= '<p class="lmp-link-description">' . esc_html($link_desc) . '</p>';
            $html .= '</div></li>';
        }
        $html .= '</ul>';
        wp_reset_postdata();
        return $html;
    }
    
    public function filter_page_content($content) {
        if (has_shortcode($content, 'list_links')) return $content;
        $page_id = $this->options['page_id'] ?? 0;
        if ($page_id > 0 && is_page($page_id) && in_the_loop() && is_main_query()) {
            return $content . do_shortcode('[list_links]');
        }
        return $content;
    }

    public function handle_export() {
        if (!isset($_POST['lmp_export_nonce_field']) || !wp_verify_nonce($_POST['lmp_export_nonce_field'], 'lmp_export_nonce') || !current_user_can('manage_options')) wp_die('Falha de segurança.');
        $data = ['categories' => [], 'links' => []]; $categories = get_terms(['taxonomy' => 'link_category', 'hide_empty' => false]);
        foreach ($categories as $cat) $data['categories'][] = ['name' => $cat->name, 'slug' => $cat->slug, 'description' => $cat->description];
        $query = new WP_Query(['post_type' => 'link', 'posts_per_page' => -1]);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post(); $id = get_the_ID(); $terms = get_the_terms($id, 'link_category'); $cat_slugs = [];
                if ($terms && !is_wp_error($terms)) { foreach ($terms as $term) $cat_slugs[] = $term->slug; }
                $data['links'][] = ['name' => get_the_title(), 'url' => get_post_meta($id, 'link_url', true), 'description' => get_post_meta($id, 'link_description', true), 'target' => get_post_meta($id, 'link_target', true), 'category_slugs' => $cat_slugs];
            }
        }
        wp_reset_postdata();
        header('Content-Type: application/json'); header('Content-Disposition: attachment; filename=lmp-export-'.date('Y-m-d').'.json'); echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit;
    }
    
    public function handle_ajax_import() {
        if (!check_ajax_referer('lmp_import_nonce', 'nonce', false)) wp_send_json_error(['message' => 'Falha de segurança.']);
        $result = $this->perform_import();
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        else wp_send_json_success(['message' => sprintf('Importação concluída! %d categorias e %d links adicionados.', $result['cats'], $result['links'])]);
    }
    
    private function perform_import() {
        if (!current_user_can('manage_options')) return new WP_Error('permission_denied', 'Você não tem permissão.');
        $json_content = '';
        if (isset($_FILES['lmp_import_file']) && $_FILES['lmp_import_file']['error'] === UPLOAD_ERR_OK) { $json_content = file_get_contents($_FILES['lmp_import_file']['tmp_name']);
        } elseif (!empty($_POST['lmp_import_text'])) { $json_content = stripslashes($_POST['lmp_import_text']); }
        if (empty($json_content)) return new WP_Error('no_data', 'Nenhum dado JSON fornecido.');
        $data = json_decode($json_content, true); if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) return new WP_Error('invalid_json', 'Formato JSON inválido.');
        $imported_cats = 0; $imported_links = 0; $slug_to_id_map = [];
        if (!empty($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $cat_data) {
                if (empty($cat_data['name']) || empty($cat_data['slug'])) continue;
                $term = term_exists($cat_data['slug'], 'link_category');
                if ($term === 0 || $term === null) {
                    $new_term = wp_insert_term(sanitize_text_field($cat_data['name']), 'link_category', ['slug' => sanitize_title($cat_data['slug']), 'description' => sanitize_textarea_field($cat_data['description'] ?? '')]);
                    if (!is_wp_error($new_term)) { $slug_to_id_map[$cat_data['slug']] = $new_term['term_id']; $imported_cats++; }
                } else $slug_to_id_map[$cat_data['slug']] = $term['term_id'];
            }
        }
        if (!empty($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as $link_data) {
                if (empty($link_data['url']) || post_exists($link_data['name'], '', '', 'link')) continue;
                $post_data = ['post_title' => sanitize_text_field($link_data['name']), 'post_type' => 'link', 'post_status' => 'publish'];
                $new_post_id = wp_insert_post($post_data, true);
                if (!is_wp_error($new_post_id)) {
                    update_post_meta($new_post_id, 'link_url', esc_url_raw($link_data['url']));
                    update_post_meta($new_post_id, 'link_description', sanitize_textarea_field($link_data['description'] ?? ''));
                    update_post_meta($new_post_id, 'link_target', sanitize_key($link_data['target'] ?? '_blank'));
                    $category_ids = [];
                    if (!empty($link_data['category_slugs']) && is_array($link_data['category_slugs'])) {
                        foreach ($link_data['category_slugs'] as $slug) {
                            if (isset($slug_to_id_map[$slug])) $category_ids[] = $slug_to_id_map[$slug];
                            else { $term = get_term_by('slug', $slug, 'link_category'); if ($term) $category_ids[] = $term->term_id; }
                        }
                    }
                    if (!empty($category_ids)) wp_set_object_terms($new_post_id, $category_ids, 'link_category');
                    $imported_links++;
                }
            }
        }
        return ['cats' => $imported_cats, 'links' => $imported_links];
    }
    
    public function register_widget() { register_widget('LinkManagerPro_Widget'); }
}

class LinkManagerPro_Widget extends WP_Widget {
    public function __construct() { parent::__construct('lmp_category_widget', __('LMP: Categorias de Links', 'link-manager-pro'), ['description' => __('Exibe uma lista de categorias de links.', 'link-manager-pro')]); }
    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title'] ?? __('Categorias de Links', 'link-manager-pro'));
        $options = get_option('lmp_settings', []); $page_id = $options['page_id'] ?? 0;
        if (!$page_id) { if (current_user_can('manage_options')) echo $args['before_widget'] . '<p>Widget de Links: Página de destino não configurada.</p>' . $args['after_widget']; return; }
        $page_url = get_permalink($page_id);
        echo $args['before_widget']; if (!empty($title)) echo $args['before_title'] . esc_html($title) . $args['after_title'];
        $categories = get_terms(['taxonomy' => 'link_category', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
        if (!empty($categories) && !is_wp_error($categories)) {
            echo '<div class="lmp-widget-list lmp-unified-container"><ul class="lmp-item-list">';
            echo '<li class="lmp-item"><a href="' . esc_url($page_url) . '">' . __('Todos os Links', 'link-manager-pro') . '</a></li>';
            foreach ($categories as $category) {
                $link = add_query_arg('link_cat', $category->term_id, $page_url);
                echo '<li class="lmp-item"><a href="' . esc_url($link) . '">' . esc_html($category->name) . ' <span class="count">(' . $category->count . ')</span></a></li>';
            }
            echo '</ul></div>';
        }
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Categorias de Links', 'link-manager-pro');
        ?>
        <p><label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Título:', 'link-manager-pro'); ?></label><input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>"></p>
        <p><?php printf(wp_kses(__('Configure a página de destino nas <a href="%s">opções do plugin</a>.', 'link-manager-pro'), ['a' => ['href' => []]]), esc_url(admin_url('edit.php?post_type=link&page=link_manager_pro_options'))); ?></p>
        <?php
    }
    public function update($new_instance, $old_instance) {
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : ''; return $instance;
    }
}

LinkManagerProModerno::get_instance();