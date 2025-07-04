jQuery(document).ready(function ($) {

    const CHAT_HISTORY_KEY = 'pouyawp_chat_history_v8';
    const SESSION_ID_KEY = 'pouyawp_chat_session_id_v8';
    const WELCOME_POPUP_CLOSED_KEY = 'pouyawp_welcome_closed_v8';
    let chatVisible = false;
    let isTyping = false;
    let chatSessionId = getSessionId();
    let messagePollingInterval = null;

    function initChat() {
        $('body').addClass('pouyawp-btn-style-' + pouyawp_chat_params.button_style);
        loadChatHistory();
        bindEvents();
        if (pouyawp_chat_params.welcome_enabled && !localStorage.getItem(WELCOME_POPUP_CLOSED_KEY)) {
            setTimeout(showWelcomePopup, 2500);
        }
    }

    function bindEvents() {
        $('#pouyawp-chat-button, #pouyawp-chat-close, .pouyawp-chat-overlay').on('click', toggleChat);
        $('#pouyawp-chat-send').on('click', sendMessage);
        $('#pouyawp-contact-operator-btn').on('click', requestOperator);
        $('.pouyawp-welcome-close').on('click', function (e) {
            e.stopPropagation();
            $('.pouyawp-welcome-popup').removeClass('show');
            localStorage.setItem(WELCOME_POPUP_CLOSED_KEY, 'true');
        });
        $('#pouyawp-chat-input').on('keypress', function (e) { if (e.which === 13 && !e.shiftKey) { e.preventDefault(); sendMessage(); } }).on('input', autoResizeTextarea);
        $(document).on('keydown', function (e) { if (e.keyCode === 27 && chatVisible) toggleChat(); });
    }

    function toggleChat() {
        const $container = $('#pouyawp-chat-container');
        const $overlay = $('.pouyawp-chat-overlay');
        chatVisible = !chatVisible;
        if (chatVisible) {
            $('.pouyawp-welcome-popup').removeClass('show');
            $container.css('display', 'flex').addClass('pouyawp-chat-visible').removeClass('pouyawp-chat-closing');
            $overlay.addClass('active');
            setTimeout(() => { $('#pouyawp-chat-input').focus(); scrollToBottom(true); }, 50);
            startMessagePolling();
        } else {
            $container.addClass('pouyawp-chat-closing').removeClass('pouyawp-chat-visible');
            $overlay.removeClass('active');
            setTimeout(() => $container.css('display', 'none'), 300);
            stopMessagePolling();
        }
    }

    function sendMessage() {
        const $input = $('#pouyawp-chat-input');
        const messageText = $input.val().trim();
        if (!messageText || isTyping) return;

        addMessage(messageText, 'user');
        saveMessageToHistory({ text: messageText, sender: 'user' });
        $input.val('').trigger('input');
        showTypingIndicator();

        $.ajax({
            url: pouyawp_chat_params.ajax_url, type: 'POST',
            data: { action: 'pouyawp_ai_chat', message: messageText, session_id: chatSessionId, nonce: pouyawp_chat_params.nonce },
            success: function (response) {
                hideTypingIndicator();
                if (response.success && response.data.response) {
                    addMessage(response.data.response, 'bot');
                    saveMessageToHistory({ text: response.data.response, sender: 'bot' });
                } else {
                    const errorMessage = response.data.response || 'خطایی در پاسخگویی ربات رخ داد.';
                    addMessage(errorMessage, 'bot');
                }
            },
            error: function () {
                hideTypingIndicator();
                addMessage('مشکل در ارتباط با سرور. لطفا از اتصال پراکسی خود اطمینان حاصل کنید.', 'bot');
            }
        });
    }

    function requestOperator() {
        showTypingIndicator();
        $.ajax({
            url: pouyawp_chat_params.ajax_url, type: 'POST',
            data: { action: 'pouyawp_contact_operator', session_id: chatSessionId, nonce: pouyawp_chat_params.nonce },
            success: function (response) { if (response.success) addMessage(response.data.message, 'system'); },
            error: function () { addMessage('ارتباط با سرور برای درخواست اپراتور برقرار نشد.', 'system'); },
            complete: function () { hideTypingIndicator(); }
        });
    }

    function startMessagePolling() {
        if (messagePollingInterval) return;
        checkForNewMessages();
        messagePollingInterval = setInterval(checkForNewMessages, 7000);
    }

    function stopMessagePolling() {
        clearInterval(messagePollingInterval);
        messagePollingInterval = null;
    }

    function checkForNewMessages() {
        const lastId = $('#pouyawp-chat-messages .pouyawp-message[data-id]').last().data('id') || 0;
        $.ajax({
            url: pouyawp_chat_params.ajax_url, type: 'POST',
            data: { action: 'pouyawp_check_for_messages', session_id: chatSessionId, last_id: lastId, nonce: pouyawp_chat_params.nonce },
            success: function (response) {
                if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                    response.data.forEach(msg => {
                        addMessage(msg.message, 'operator-message', msg.id);
                        saveMessageToHistory({ text: msg.message, sender: 'operator', id: msg.id });
                    });
                }
            }
        });
    }

    function addMessage(text, sender, messageId = null) {
        const $messagesContainer = $('#pouyawp-chat-messages');
        const messageClass = `pouyawp-message-${sender}`;
        const formattedText = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
        const messageElement = $(`<div class="pouyawp-message ${messageClass}" ${messageId ? `data-id="${messageId}"` : ''}>${formattedText}</div>`);
        $messagesContainer.append(messageElement);
        scrollToBottom();
        return messageElement;
    }

    function showTypingIndicator() {
        if ($('#typing-indicator').length) return;
        const typingElement = `<div class="pouyawp-message pouyawp-typing" id="typing-indicator"><div class="pouyawp-typing-dot"></div><div class="pouyawp-typing-dot"></div><div class="pouyawp-typing-dot"></div></div>`;
        $('#pouyawp-chat-messages').append(typingElement); scrollToBottom(); isTyping = true;
    }

    function hideTypingIndicator() {
        $('#typing-indicator').remove(); isTyping = false;
    }

    function showWelcomePopup() {
        $('.pouyawp-welcome-popup').addClass('show');
    }

    function autoResizeTextarea() {
        this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';
    }

    function scrollToBottom(force = false) {
        const $container = $('#pouyawp-chat-messages');
        if (force) { $container.scrollTop($container[0].scrollHeight); return; }
        $container.animate({ scrollTop: $container[0].scrollHeight }, 300);
    }

    function getSessionId() {
        let id = localStorage.getItem(SESSION_ID_KEY);
        if (!id) { id = Date.now() + '-' + Math.random().toString(36).substr(2, 9); localStorage.setItem(SESSION_ID_KEY, id); }
        return id;
    }

    function saveMessageToHistory(messageObject) {
        let history = getChatHistory();
        history.push(messageObject);
        localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(history));
    }

    function getChatHistory() {
        const storedData = localStorage.getItem(CHAT_HISTORY_KEY);
        if (!storedData) return [];
        try { return JSON.parse(storedData); } catch (e) { return []; }
    }

    function loadChatHistory() {
        const history = getChatHistory();
        if (history.length > 0) {
            history.forEach(msg => {
                if (msg.text && msg.sender) {
                    addMessage(msg.text, msg.sender, msg.id);
                }
            });
        } else {
            setTimeout(() => addMessage(pouyawp_chat_params.initial_bot_message, 'bot'), 1500);
        }
        scrollToBottom(true);
    }

    initChat();
});