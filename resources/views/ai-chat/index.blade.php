@extends('layouts.app')
@section('page-title', 'Distora AI Assistant')

@section('content')
<div style="display:flex; flex-direction:column; height:calc(100vh - 140px); max-height:calc(100vh - 140px);">
    <div class="card" style="flex:1; display:flex; flex-direction:column; padding:0; overflow:hidden;">
        <!-- Chat History Area -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1.5rem; border-bottom:1px solid var(--border-color); background:var(--bg-card-hover);">
            <div style="display:flex; align-items:center; gap:1rem;">
                <span style="font-size:0.8rem; font-weight:600; color:var(--text-muted);">Riwayat Obrolan</span>
                <span id="token-status" style="font-size:0.65rem; color:var(--text-muted); background:var(--bg-darker); padding:0.2rem 0.5rem; border-radius:4px; border:1px solid var(--border-color); display:none;" title="Sisa Token AI (Limit: 6000 TPM)">
                    Sisa Token: <strong id="token-count" style="color:var(--text-primary);">...</strong>
                </span>
            </div>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <select id="model-select" style="font-size:0.75rem; background:var(--bg-darker); color:var(--text-primary); border:1px solid var(--border-color); padding:0.3rem 0.6rem; border-radius:6px; cursor:pointer; outline:none; font-weight:500;">
                    <option value="llama-3.1-8b-instant">Llama 3.1 8B (Instant)</option>
                </select>
                <button onclick="clearChat()" style="font-size:0.75rem; background:transparent; color:var(--accent-red); border:1px solid rgba(239,68,68,0.3); padding:0.3rem 0.6rem; border-radius:6px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">Hapus Riwayat</button>
            </div>
        </div>
        <div id="chat-history" style="flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem; scroll-behavior:smooth;">
            
            <!-- Welcome Message -->
            <div style="display:flex; align-items:flex-start; gap:0.75rem;">
                <div style="width:38px; height:38px; border-radius:8px; background:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div style="background:var(--bg-card); border-radius:0 12px 12px 12px; padding:1.25rem 1.5rem; max-width:85%; border:1px solid rgba(255,255,255,0.08); box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                    <p style="font-size:1.05rem; font-weight:600; margin-bottom:0.75rem; color:#fff;">Halo! Saya Distora AI Assistant 👋</p>
                    <p style="font-size:0.95rem; color:var(--text-primary); line-height:1.7;">Saya dapat menjawab pertanyaan Anda seputar performa penjualan, produk terlaris, dan kinerja salesman berdasarkan data sistem secara real-time. Apa yang ingin Anda ketahui?</p>
                    
                    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.75rem;">
                        <button onclick="setPrompt('Apa saja 5 produk paling laris bulan ini?')" style="font-size:0.75rem; background:var(--bg-darker); color:var(--text-secondary); border:1px solid var(--border-color); padding:0.4rem 0.75rem; border-radius:100px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--text-primary)'" onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-secondary)'">Apa saja 5 produk paling laris?</button>
                        <button onclick="setPrompt('Siapa salesman dengan performa terbaik?')" style="font-size:0.75rem; background:var(--bg-darker); color:var(--text-secondary); border:1px solid var(--border-color); padding:0.4rem 0.75rem; border-radius:100px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--text-primary)'" onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-secondary)'">Siapa salesman terbaik?</button>
                        <button onclick="setPrompt('Berikan ringkasan performa bulan ini')" style="font-size:0.75rem; background:var(--bg-darker); color:var(--text-secondary); border:1px solid var(--border-color); padding:0.4rem 0.75rem; border-radius:100px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--text-primary)'" onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-secondary)'">Ringkasan performa bulan ini</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- Loading Indicator -->
        <div id="loading-indicator" style="display:none; padding:0.5rem 1.5rem;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <div style="width:38px; height:38px; border-radius:8px; background:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div style="background:var(--bg-card-hover); border-radius:0 12px 12px 12px; padding:0.75rem 1.25rem; border:1px solid var(--border-color);">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <div class="ai-typing-dots">
                            <span></span><span></span><span></span>
                        </div>
                        <span style="font-size:0.8rem; color:var(--text-muted);">AI sedang berpikir...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div style="padding:1rem 1.5rem; border-top:1px solid var(--border-color); background:var(--bg-card);">
            <form id="chat-form" onsubmit="sendMessage(event)" style="display:flex; gap:0.75rem; align-items:center;">
                <input type="text" id="chat-input" required
                    class="form-input"
                    style="flex:1; border-radius:100px; padding:0.85rem 1.5rem; font-size:0.95rem; background:var(--bg-darker); color:#fff;"
                    placeholder="Tanya soal penjualan, produk, atau salesman disini..." autocomplete="off">
                
                <button type="submit" id="send-button" class="btn btn-primary" style="border-radius:100px; width:44px; height:44px; padding:0; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                        <path d="M3.478 2.404a.75.75 0 00-.926.941l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.404z" />
                    </svg>
                </button>
            </form>
            <div style="text-align:center; margin-top:0.5rem;">
                <span style="font-size:0.65rem; color:var(--text-muted);">Distora AI dapat membuat kesalahan. Selalu verifikasi data kritis pada dashboard utama.</span>
            </div>
        </div>

    </div>
</div>

<!-- Marked.js for Markdown parsing -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
    .ai-typing-dots { display:flex; gap:4px; align-items:center; }
    .ai-typing-dots span {
        width:6px; height:6px; border-radius:50%; background:var(--primary-light);
        animation: dotBounce 1.2s infinite;
    }
    .ai-typing-dots span:nth-child(2) { animation-delay: 0.15s; }
    .ai-typing-dots span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes dotBounce {
        0%, 60%, 100% { transform: translateY(0); opacity:0.4; }
        30% { transform: translateY(-6px); opacity:1; }
    }

    /* Markdown prose styling inside AI messages */
    .ai-prose p { margin:0.5em 0; line-height:1.7; font-size:0.95rem; color:var(--text-primary); }
    .ai-prose p:first-child { margin-top:0; }
    .ai-prose p:last-child { margin-bottom:0; }
    .ai-prose ul, .ai-prose ol { margin:0.5em 0; padding-left:1.5em; font-size:0.95rem; color:var(--text-primary); }
    .ai-prose li { margin:0.25em 0; line-height:1.6; }
    .ai-prose strong { color:#fff; font-weight:600; }
    .ai-prose code { background:var(--bg-darker); padding:0.2em 0.4em; border-radius:4px; font-size:0.85em; color:#e2e8f0; border: 1px solid var(--border-color); }
    .ai-prose table { width:100%; border-collapse:collapse; margin:0.75em 0; font-size:0.9rem; }
    .ai-prose th { text-align:left; padding:0.6rem 0.8rem; border-bottom:1px solid var(--border-color); color:var(--text-muted); font-size:0.8rem; text-transform:uppercase; }
    .ai-prose td { padding:0.6rem 0.8rem; border-bottom:1px solid rgba(51,65,85,0.3); color:var(--text-primary); }
    .ai-prose h1,.ai-prose h2,.ai-prose h3,.ai-prose h4 { margin:0.8em 0 0.4em; color:#fff; font-weight:600; }
    .ai-prose h3 { font-size:1.1rem; }
    .ai-prose h4 { font-size:1rem; }
    .ai-prose blockquote { border-left:3px solid var(--primary); padding-left:1rem; margin:0.75em 0; color:var(--text-secondary); font-style:italic; }

    /* Custom scrollbar */
    #chat-history::-webkit-scrollbar { width:5px; }
    #chat-history::-webkit-scrollbar-track { background:transparent; }
    #chat-history::-webkit-scrollbar-thumb { background:var(--border-color); border-radius:10px; }
</style>

<script>
    const chatInput = document.getElementById('chat-input');
    const chatHistory = document.getElementById('chat-history');
    const loadingIndicator = document.getElementById('loading-indicator');
    const sendButton = document.getElementById('send-button');

    const modelSelect = document.getElementById('model-select');

    // Initialize conversation history from LocalStorage
    let conversationHistory = JSON.parse(localStorage.getItem('ai_chat_history') || '[]');

    // Render initial history and load saved model
    window.addEventListener('DOMContentLoaded', () => {
        const savedModel = localStorage.getItem('ai_chat_model') || 'llama-3.1-8b-instant';
        modelSelect.value = savedModel;

        modelSelect.addEventListener('change', () => {
            localStorage.setItem('ai_chat_model', modelSelect.value);
        });

        if (conversationHistory.length > 0) {
            conversationHistory.forEach(msg => {
                if (msg.role === 'user') {
                    appendUserMessageUI(msg.content);
                } else if (msg.role === 'assistant') {
                    appendAiMessageUI(msg.content);
                }
            });
            scrollToBottom();
        }
    });

    function setPrompt(text) {
        chatInput.value = text;
        chatInput.focus();
    }

    function clearChat() {
        if(confirm('Apakah Anda yakin ingin menghapus seluruh riwayat percakapan?')) {
            localStorage.removeItem('ai_chat_history');
            conversationHistory = [];
            // Remove all messages except the first welcome message
            while (chatHistory.children.length > 1) {
                chatHistory.removeChild(chatHistory.lastChild);
            }
        }
    }

    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function sanitizeMarkdownHtml(html) {
        const template = document.createElement('template');
        template.innerHTML = html;

        template.content.querySelectorAll('script, style, iframe, object, embed, link, meta').forEach(node => node.remove());

        template.content.querySelectorAll('*').forEach(node => {
            [...node.attributes].forEach(attribute => {
                const name = attribute.name.toLowerCase();
                const value = attribute.value.trim().toLowerCase();

                if (name.startsWith('on') || ((name === 'href' || name === 'src') && value.startsWith('javascript:'))) {
                    node.removeAttribute(attribute.name);
                }
            });
        });

        return template.innerHTML;
    }

    function scrollToBottom() {
        chatHistory.scrollTop = chatHistory.scrollHeight;
    }

    function appendUserMessageUI(text) {
        const div = document.createElement('div');
        div.style.cssText = 'display:flex; align-items:flex-start; gap:0.75rem; justify-content:flex-end;';
        div.innerHTML = `
            <div style="background:var(--primary); border-radius:12px 0 12px 12px; padding:0.85rem 1.25rem; max-width:70%; color:white; box-shadow: 0 4px 12px rgba(37,99,235,0.15); border: 1px solid var(--primary-dark);">
                <p style="font-size:0.95rem; line-height:1.6; margin:0;">${escapeHtml(text)}</p>
            </div>
            <div style="width:38px; height:38px; border-radius:10px; background:var(--bg-card-hover); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="var(--text-secondary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
        `;
        chatHistory.appendChild(div);
        scrollToBottom();
    }

    function appendAiMessageUI(markdownText) {
        const parsedHtml = sanitizeMarkdownHtml(marked.parse(markdownText));
        const div = document.createElement('div');
        div.style.cssText = 'display:flex; align-items:flex-start; gap:0.75rem;';
        div.innerHTML = `
            <div style="width:38px; height:38px; border-radius:8px; background:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <div class="ai-prose" style="background:var(--bg-card); border-radius:0 12px 12px 12px; padding:1.25rem 1.5rem; max-width:85%; border:1px solid rgba(255,255,255,0.08); font-size:0.95rem; color:var(--text-primary); overflow-x:auto; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                ${parsedHtml}
            </div>
        `;
        chatHistory.appendChild(div);
        scrollToBottom();
    }

    function appendErrorMessage(text) {
        const div = document.createElement('div');
        div.style.cssText = 'display:flex; justify-content:center;';
        div.innerHTML = `
            <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:var(--accent-red); font-size:0.8rem; padding:0.5rem 1rem; border-radius:8px; display:flex; align-items:center; gap:0.5rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                ${text}
            </div>
        `;
        chatHistory.appendChild(div);
        scrollToBottom();
    }

    async function sendMessage(e) {
        e.preventDefault();
        
        const message = chatInput.value.trim();
        if (!message) return;

        chatInput.value = '';
        
        // Update History
        conversationHistory.push({ role: 'user', content: message });
        localStorage.setItem('ai_chat_history', JSON.stringify(conversationHistory));
        
        appendUserMessageUI(message);
        
        chatInput.disabled = true;
        sendButton.disabled = true;
        sendButton.style.opacity = '0.5';
        loadingIndicator.style.display = 'block';
        scrollToBottom();

        try {
            const response = await fetch('{{ route("ai-chat.ask") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ 
                    history: conversationHistory,
                    model: modelSelect.value
                })
            });

            const data = await response.json();

            if (!response.ok) {
                // Remove failed message from history
                conversationHistory.pop();
                localStorage.setItem('ai_chat_history', JSON.stringify(conversationHistory));
                throw new Error(data.error || 'Terjadi kesalahan pada server.');
            }

            // Append AI response to history
            conversationHistory.push({ role: 'assistant', content: data.reply });
            localStorage.setItem('ai_chat_history', JSON.stringify(conversationHistory));
            
            appendAiMessageUI(data.reply);

            if (data.remaining_tokens) {
                document.getElementById('token-status').style.display = 'block';
                let tCount = document.getElementById('token-count');
                tCount.innerText = data.remaining_tokens;
                // If tokens < 1000, make it red to warn the user
                if(parseInt(data.remaining_tokens) < 1000) {
                    tCount.style.color = 'var(--accent-red)';
                } else {
                    tCount.style.color = 'var(--primary-light)';
                }
            }

        } catch (error) {
            appendErrorMessage(error.message);
        } finally {
            chatInput.disabled = false;
            sendButton.disabled = false;
            sendButton.style.opacity = '1';
            loadingIndicator.style.display = 'none';
            chatInput.focus();
        }
    }
</script>
@endsection
