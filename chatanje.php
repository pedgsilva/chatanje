<?php
/**
 * Plugin Name: ChatANJE
 * Description: Chatbot inteligente para www.anje.pt
 * Version: 1.3.0
 * Author: Pedro Silva
 */

if (!defined('ABSPATH')) exit;

class ChatANJE {
    
    private $option_key = 'chatanje_settings';
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_chatanje_chat', [$this, 'handle_chat']);
        add_action('wp_ajax_nopriv_chatanje_chat', [$this, 'handle_chat']);
    }
    
    public function enqueue_assets() {
        // CSS inline
        wp_register_style('chatanje-css', false);
        wp_enqueue_style('chatanje-css');
        wp_add_inline_style('chatanje-css', $this->get_css());
        
        // JS inline com dados
        wp_register_script('chatanje-js', false);
        wp_enqueue_script('chatanje-js');
        
        $settings = get_option($this->option_key, []);
        $welcome = !empty($settings['welcome_message']) ? $settings['welcome_message'] : "Olá! 👋 Sou o assistente virtual da ANJE.\n\nPosso ajudar com:\n• 🏛️ Sobre a ANJE\n• 👥 Órgãos sociais\n• 📋 Programas\n• 📞 Contactos\n\nO que procura?";
        
        $js_data = json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatanje_nonce'),
            'welcome' => $welcome,
        ]);
        
        $js_code = "
        var chatanjeData = {$js_data};
        (function($) {
            'use strict';
            var isWaiting = false;
            
            $(document).on('click', '#chatanje-toggle', function() {
                var win = $('#chatanje-window');
                if (win.is(':visible')) { win.slideUp(200); $(this).html('💬'); }
                else { win.slideDown(250); $(this).html('✕'); $('#chatanje-input').focus(); }
            });
            
            $(document).on('click', '#chatanje-close', function() {
                $('#chatanje-window').slideUp(200);
                $('#chatanje-toggle').html('💬');
            });
            
            $(document).on('click', '#chatanje-send', doSend);
            $(document).on('keypress', '#chatanje-input', function(e) { if (e.key === 'Enter') doSend(); });
            
            // Welcome
            if (chatanjeData.welcome) addBotMsg(chatanjeData.welcome);
            
            function doSend() {
                if (isWaiting) return;
                var inp = $('#chatanje-input');
                var msg = inp.val().trim();
                if (!msg) return;
                isWaiting = true;
                $('#chatanje-send').prop('disabled', true);
                addUserMsg(msg);
                inp.val('');
                var tid = 't' + Date.now();
                addTyping(tid);
                $.ajax({
                    url: chatanjeData.ajaxUrl,
                    method: 'POST',
                    data: { action: 'chatanje_chat', message: msg, nonce: chatanjeData.nonce },
                    timeout: 30000,
                    success: function(res) { removeTyping(tid); addBotMsg(res.data.response || 'Erro.'); },
                    error: function() { removeTyping(tid); addBotMsg('Erro de ligação.'); },
                    complete: function() { isWaiting = false; $('#chatanje-send').prop('disabled', false); inp.focus(); }
                });
            }
            
            function addUserMsg(t) { appendMsg(t, 'chatanje-user'); }
            function addBotMsg(t) { appendMsg(fmt(t), 'chatanje-bot'); }
            function appendMsg(h, c) { $('<div class=\"chatanje-msg ' + c + '\"></div>').html(h).appendTo('#chatanje-messages')[0].scrollIntoView({behavior:'smooth'}); }
            function addTyping(id) { $('<div class=\"chatanze-typing\" id=\"'+id+'\"><span></span><span></span><span></span></div>').appendTo('#chatanje-messages'); }
            function removeTyping(id) { $('#'+id).remove(); }
            function fmt(t) {
                if (!t) return '';
                var h = t.replace(/&#8211;/g,'–').replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>');
                h = h.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                h = h.replace(/\\*\\*(.+?)\\*\\*/g,'<strong>$1</strong>');
                h = h.replace(/(https?:\\/\\/[^\\s<>\"']+)/g,'<a href=\"$1\" target=\"_blank\" rel=\"noopener\">$1</a>');
                return h.replace(/\\n/g,'<br>');
            }
        })(jQuery);
        ";
        
        wp_add_inline_script('chatanje-js', $js_code);
    }
    
    private function get_css() {
        return '#chatanje-widget{position:fixed;bottom:20px;right:20px;z-index:999999;font-family:-apple-system,BlinkMacSystemFont,sans-serif}#chatanje-toggle{width:60px;height:60px;border-radius:50%;border:none;background:#007bff;color:#fff;cursor:pointer;box-shadow:0 4px 16px rgba(0,123,255,.4);font-size:28px;display:flex;align-items:center;justify-content:center;transition:transform .3s}#chatanje-toggle:hover{transform:scale(1.1)}#chatanje-window{position:absolute;bottom:75px;right:0;width:380px;max-width:calc(100vw - 40px);height:520px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);display:flex;flex-direction:column;overflow:hidden;animation:slideUp .3s ease}@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}#chatanje-header{background:#007bff;color:#fff;padding:14px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0}#chatanje-header strong{display:block;font-size:14px}#chatanje-header small{font-size:11px;opacity:.8}#chatanje-header-info{flex:1}#chatanje-avatar{font-size:28px;width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center}#chatanje-close{background:0 0;border:none;color:#fff;font-size:20px;cursor:pointer;padding:4px;opacity:.8}#chatanje-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#f8f9fa}.chatanje-msg{max-width:85%;padding:10px 14px;border-radius:12px;font-size:13.5px;line-height:1.55;word-wrap:break-word}.chatanje-bot{background:#fff;color:#333;align-self:flex-start;border-bottom-left-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08)}.chatanje-user{background:#007bff;color:#fff;align-self:flex-end;border-bottom-right-radius:4px}.chatanje-bot a{color:#0066cc;text-decoration:underline;word-break:break-all}.chatanje-bot strong{color:#1a1a2e}#chatanje-input-area{display:flex;padding:10px 12px;background:#fff;border-top:1px solid #e9ecef;gap:8px;flex-shrink:0}#chatanje-input{flex:1;padding:10px 14px;border:1px solid #ddd;border-radius:20px;outline:0;font-size:13.5px}#chatanje-input:focus{border-color:#007bff}#chatanje-send{width:40px;height:40px;border-radius:50%;border:none;background:#007bff;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px}#chatanje-send:hover{background:#0056b3}#chatanje-send:disabled{background:#ccc;cursor:not-allowed}.chatanze-typing{display:flex;gap:4px;padding:10px 14px;background:#fff;border-radius:12px;align-self:flex-start;box-shadow:0 1px 3px rgba(0,0,0,.08)}.chatanze-typing span{width:7px;height:7px;background:#999;border-radius:50%;animation:blink 1.2s infinite}.chatanze-typing span:nth-child(2){animation-delay:.2s}.chatanze-typing span:nth-child(3){animation-delay:.4s}@keyframes blink{0%,80%,100%{opacity:.2}40%{opacity:1}}@media(max-width:480px){#chatanje-window{width:calc(100vw - 20px);height:calc(100vh - 100px);right:-10px}}';
    }
    
    public function render_chatbot() {
        ?>
        <div id="chatanje-widget">
            <button id="chatanje-toggle" aria-label="Abrir chatbot">&#128172;</button>
            <div id="chatanje-window" style="display:none;">
                <div id="chatanje-header">
                    <div id="chatanje-avatar">&#129302;</div>
                    <div id="chatanje-header-info">
                        <strong>ChatBot ANJE</strong>
                        <small>Online</small>
                    </div>
                    <button id="chatanje-close" aria-label="Fechar">&#10005;</button>
                </div>
                <div id="chatanje-messages"></div>
                <div id="chatanje-input-area">
                    <input type="text" id="chatanje-input" placeholder="Escreva a sua pergunta..." autocomplete="off">
                    <button id="chatanje-send" aria-label="Enviar">&#10148;</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function handle_chat() {
        check_ajax_referer('chatanje_nonce', 'nonce');
        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_send_json_error('Mensagem vazia', 400);
        }
        
        $settings = get_option($this->option_key, []);
        $openrouter_key = $settings['openrouter_key'] ?? '';
        
        if (empty($openrouter_key)) {
            wp_send_json_success(['response' => '⚠️ Configure a OpenRouter API Key em Definições > ChatANJE']);
        }
        
        $response = $this->call_openrouter($message, $settings);
        wp_send_json_success(['response' => $response]);
    }
    
    private function call_openrouter($message, $settings) {
        $api_key = $settings['openrouter_key'] ?? '';
        if (empty($api_key)) return 'API Key não configurada.';
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => 25,
            'headers' => ['Authorization' => "Bearer {$api_key}", 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => $settings['model'] ?? 'openrouter/owl-alpha',
                'messages' => [
                    ['role' => 'system', 'content' => $this->get_system_prompt()],
                    ['role' => 'user', 'content' => "Pergunta: {$message}"],
                ],
                'temperature' => 0.1,
                'max_tokens' => 600,
            ]),
        ]);
        
        if (is_wp_error($response)) return 'Erro de ligação.';
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['error'])) return 'Erro: ' . ($data['error']['message'] ?? 'Erro desconhecido');
        return $data['choices'][0]['message']['content'] ?? 'Erro na resposta.';
    }
    
    private function get_system_prompt() {
        return "Es o assistente virtual da ANJE (anje.pt).\n"
            . "\nSOBRE: A ANJE é uma associação de direito privado e utilidade pública que representa jovens empresários portugueses.\n"
            . "\nCONTACTOS: anje@anje.pt | (+351) 220 108 000 | Casa do Farol, Rua Paulo da Gama, 4169-006 Porto\n"
            . "\nPAGINAS (INCLUI URL COMPLETO NAS RESPOSTAS):\n"
            . "- Estatutos: https://anje.pt/anje/estatutos/\n"
            . "- Associados: https://anje.pt/associados/\n"
            . "- Incubacao: https://anje.pt/incubacao/\n"
            . "- Formacao: https://anje.pt/formacao/\n"
            . "- Premio: https://anje.pt/premio-do-jovem-empreendedor/\n"
            . "- Orgaos Sociais: https://anje.pt/orgaos-sociais/\n"
            . "- Contactos: https://anje.pt/contactos/\n"
            . "- Blog: https://anje.pt/blog/\n"
            . "- Comunicacao: https://anje.pt/comunicacao/\n"
            . "- Apresentacao: https://anje.pt/apresentacao/\n"
            . "- Evolucao: https://anje.pt/evolucao/\n"
            . "- Bolsas: https://anje.pt/bolsas-de-formadores/\n"
            . "\nREGRAS:\n"
            . "- Portugues de Portugal\n"
            . "- **SEMPRE inclui o URL completo** quando fala de uma página\n"
            . "- Exemplo: 'Consulta os estatutos em: https://anje.pt/anje/estatutos/'\n"
            . "- Exemplo: 'Para ser associado: https://anje.pt/associados/'\n"
            . "- Usa **negrita** para títulos\n"
            . "- Se não souberes, sugere contactar anje@anje.pt\n";
    }
    
    public function add_admin_menu() {
        add_options_page('ChatANJE', 'ChatANJE', 'manage_options', 'chatanje', [$this, 'admin_page']);
    }
    
    public function register_settings() {
        register_setting('chatanje_group', $this->option_key);
    }
    
    public function admin_page() {
        $settings = get_option($this->option_key, []);
        ?>
        <div class="wrap">
            <h1>🤖 ChatANJE - Configurações</h1>
            <form method="post" action="options.php">
                <?php settings_fields('chatanje_group'); ?>
                <table class="form-table">
                    <tr><th>OpenRouter API Key</th><td><input type="password" name="<?php echo $this->option_key; ?>[openrouter_key]" value="<?php echo esc_attr($settings['openrouter_key'] ?? ''); ?>" class="regular-text" placeholder="sk-or-..."></td></tr>
                    <tr><th>Modelo LLM</th><td><input type="text" name="<?php echo $this->option_key; ?>[model]" value="<?php echo esc_attr($settings['model'] ?? 'openrouter/owl-alpha'); ?>" class="regular-text"></td></tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
        </div>
        <?php
    }
}

new ChatANJE();
