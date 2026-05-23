/**
 * ChatANJE - Frontend JavaScript
 */
(function($) {
    'use strict';
    
    var isWaiting = false;
    
    // Toggle chat
    $('#chatanje-toggle').on('click', function() {
        var win = $('#chatanje-window');
        if (win.is(':visible')) {
            win.slideUp(200);
            $(this).text('💬');
        } else {
            win.slideDown(250);
            $(this).text('✕');
            $('#chatanje-input').focus();
        }
    });
    
    $('#chatanje-close').on('click', function() {
        $('#chatanje-window').slideUp(200);
        $('#chatanje-toggle').text('💬');
    });
    
    // Send message
    $('#chatanje-send').on('click', send);
    $('#chatanje-input').on('keypress', function(e) { if (e.key === 'Enter') send(); });
    
    // Welcome message
    if (chatanjeData.welcome) {
        addBotMsg(chatanjeData.welcome);
    }
    
    function send() {
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
            success: function(res) {
                removeTyping(tid);
                addBotMsg(res.data.response || 'Erro na resposta.');
            },
            error: function() {
                removeTyping(tid);
                addBotMsg('Erro de ligação. Tente novamente.');
            },
            complete: function() {
                isWaiting = false;
                $('#chatanje-send').prop('disabled', false);
                inp.focus();
            }
        });
    }
    
    function addUserMsg(msg) { appendMsg(msg, 'chatanje-user'); }
    function addBotMsg(msg) { appendMsg(formatText(msg), 'chatanje-bot'); }
    
    function appendMsg(html, cls) {
        var div = $('<div class="chatanje-msg ' + cls + '"></div>').html(html);
        $('#chatanje-messages').append(div);
        div[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    function addTyping(id) {
        var div = $('<div class="chatanze-typing" id="' + id + '"><span></span><span></span><span></span></div>');
        $('#chatanje-messages').append(div);
    }
    
    function removeTyping(id) { $('#' + id).remove(); }
    
    function formatText(text) {
        if (!text) return '';
        var html = text
            .replace(/&#8211;/g, '–').replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        html = html.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/(https?:\/\/[^\s<>"']+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
        html = html.replace(/\n/g, '<br>');
        return html;
    }
    
})(jQuery);
