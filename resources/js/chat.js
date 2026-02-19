const chatRoot = document.getElementById('chat-app');

if (chatRoot) {
    const messagesContainer = document.getElementById('messages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const recipientInput = document.getElementById('recipientId');
    const findRecipientButton = document.getElementById('findRecipient');
    const recipientState = document.getElementById('recipientState');
    const sendButton = document.getElementById('sendMessage');
    const formError = document.getElementById('formError');
    const connectionStatus = document.getElementById('connectionStatus');
    const startRecordButton = document.getElementById('startRecord');
    const stopRecordButton = document.getElementById('stopRecord');
    const clearVoiceButton = document.getElementById('clearVoice');
    const voicePreview = document.getElementById('voicePreview');
    const voiceState = document.getElementById('voiceState');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const authUsername = chatRoot.dataset.authUsername ?? '';
    const postUrl = chatRoot.dataset.postUrl ?? '/messages';
    const findUrl = chatRoot.dataset.findUrl ?? '/users/find';
    const seenMessageIds = new Set();

    let mediaRecorder = null;
    let recordedChunks = [];
    let recordedBlob = null;
    let previewUrl = null;

    const statusStyles = {
        neutral: 'text-slate-300',
        success: 'text-emerald-300',
        error: 'text-rose-300',
    };

    const connectionStyles = {
        connecting: 'bg-amber-500/20 text-amber-200',
        connected: 'bg-emerald-500/20 text-emerald-200',
        error: 'bg-rose-500/20 text-rose-200',
    };

    const setConnectionStatus = (text, state = 'connecting') => {
        connectionStatus.textContent = text;
        connectionStatus.classList.remove(...Object.values(connectionStyles).flatMap((value) => value.split(' ')));
        connectionStatus.classList.add(...connectionStyles[state].split(' '));
    };

    const setRecipientState = (text, state = 'neutral') => {
        recipientState.textContent = text;
        recipientState.classList.remove(...Object.values(statusStyles).flatMap((value) => value.split(' ')));
        recipientState.classList.add(...statusStyles[state].split(' '));
    };

    const showError = (text) => {
        formError.textContent = text;
        formError.classList.remove('hidden');
    };

    const clearError = () => {
        formError.textContent = '';
        formError.classList.add('hidden');
    };

    const scrollMessagesToBottom = () => {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    };

    const parseJsonSafe = async (response) => {
        try {
            return await response.json();
        } catch {
            return {};
        }
    };

    const formatTime = (message) => {
        if (message.time) {
            return message.time;
        }

        if (!message.created_at) {
            return '';
        }

        try {
            return new Date(message.created_at).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return '';
        }
    };

    const renderMessage = (message) => {
        if (!message || seenMessageIds.has(message.id)) {
            return;
        }

        seenMessageIds.add(message.id);

        const mine = message.sender_username === authUsername;
        const item = document.createElement('article');
        item.className = mine
            ? 'rounded-xl border border-emerald-500/30 bg-emerald-900/20 p-3 md:p-4'
            : 'rounded-xl border border-slate-800 bg-slate-950/60 p-3 md:p-4';
        item.dataset.messageId = String(message.id);

        const header = document.createElement('div');
        header.className = 'mb-2 flex items-center justify-between gap-2';

        const counterpart = document.createElement('p');
        counterpart.className = mine ? 'text-sm font-semibold text-emerald-300' : 'text-sm font-semibold text-cyan-300';
        counterpart.textContent = mine ? `To: ${message.recipient_username}` : `From: ${message.sender_username}`;

        const time = document.createElement('p');
        time.className = 'text-xs text-slate-400';
        time.textContent = formatTime(message);

        header.append(counterpart, time);
        item.appendChild(header);

        if (message.message) {
            const body = document.createElement('p');
            body.className = 'whitespace-pre-wrap text-sm text-slate-100';
            body.textContent = message.message;
            item.appendChild(body);
        }

        if (message.voice_url) {
            const audio = document.createElement('audio');
            audio.className = 'mt-3 w-full';
            audio.controls = true;
            audio.src = message.voice_url;
            item.appendChild(audio);
        }

        messagesContainer.appendChild(item);
    };

    const clearVoice = () => {
        recordedBlob = null;

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        voicePreview.src = '';
        voicePreview.classList.add('hidden');
        clearVoiceButton.classList.add('hidden');
        voiceState.textContent = 'No voice selected.';
    };

    const getAudioExtension = (mimeType) => {
        const normalized = mimeType.toLowerCase();

        if (normalized.includes('webm')) return 'webm';
        if (normalized.includes('ogg')) return 'ogg';
        if (normalized.includes('wav')) return 'wav';
        if (normalized.includes('mpeg') || normalized.includes('mp3')) return 'mp3';
        if (normalized.includes('mp4') || normalized.includes('m4a')) return 'm4a';

        return 'webm';
    };

    const getPreferredMimeType = () => {
        if (typeof MediaRecorder === 'undefined') {
            return null;
        }

        const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];

        return candidates.find((candidate) => MediaRecorder.isTypeSupported(candidate)) ?? null;
    };

    const startRecording = async () => {
        clearError();

        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            showError('Your browser does not support voice recording.');
            return;
        }

        try {
            clearVoice();

            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const mimeType = getPreferredMimeType();

            mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
            recordedChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = () => {
                stream.getTracks().forEach((track) => track.stop());

                if (recordedChunks.length === 0) {
                    voiceState.textContent = 'No audio captured.';
                    return;
                }

                recordedBlob = new Blob(recordedChunks, {
                    type: mediaRecorder.mimeType || 'audio/webm',
                });

                previewUrl = URL.createObjectURL(recordedBlob);
                voicePreview.src = previewUrl;
                voicePreview.classList.remove('hidden');
                clearVoiceButton.classList.remove('hidden');
                voiceState.textContent = 'Voice note is ready to send.';
            };

            mediaRecorder.start();
            startRecordButton.disabled = true;
            stopRecordButton.disabled = false;
            voiceState.textContent = 'Recording...';
        } catch {
            showError('Microphone access was denied or unavailable.');
        }
    };

    const stopRecording = () => {
        if (!mediaRecorder || mediaRecorder.state === 'inactive') {
            return;
        }

        mediaRecorder.stop();
        startRecordButton.disabled = false;
        stopRecordButton.disabled = true;
    };

    const findRecipient = async () => {
        clearError();

        const id = recipientInput.value.trim().toLowerCase();
        recipientInput.value = id;

        if (!id) {
            setRecipientState('Enter a user ID first.', 'error');
            return;
        }

        if (id === authUsername) {
            setRecipientState('You cannot send messages to yourself.', 'error');
            return;
        }

        findRecipientButton.disabled = true;

        try {
            const response = await fetch(`${findUrl}?id=${encodeURIComponent(id)}`, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            });

            const payload = await parseJsonSafe(response);

            if (!response.ok) {
                throw new Error(payload?.message ?? 'User not found.');
            }

            setRecipientState(`Found user ID: ${payload.id}`, 'success');
        } catch (error) {
            setRecipientState(error instanceof Error ? error.message : 'User not found.', 'error');
        } finally {
            findRecipientButton.disabled = false;
        }
    };

    const initializeRealtime = () => {
        if (!window.Echo || !authUsername) {
            setConnectionStatus('Realtime client is not initialized.', 'error');
            return;
        }

        const connector = window.Echo?.connector?.pusher?.connection;

        connector?.bind('connected', () => setConnectionStatus('Connected to realtime server.', 'connected'));
        connector?.bind('disconnected', () => setConnectionStatus('Realtime disconnected. Reconnecting...', 'error'));
        connector?.bind('error', () => setConnectionStatus('Realtime connection error.', 'error'));

        window.Echo.private(`chat.user.${authUsername}`).listen('.message.sent', (event) => {
            if (event?.message) {
                renderMessage(event.message);
                scrollMessagesToBottom();
            }
        });
    };

    chatForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearError();

        if (mediaRecorder && mediaRecorder.state === 'recording') {
            showError('Please stop the voice recording before sending.');
            return;
        }

        const recipientId = recipientInput.value.trim().toLowerCase();
        recipientInput.value = recipientId;
        const text = messageInput.value.trim();

        if (!recipientId) {
            showError('Recipient ID is required.');
            return;
        }

        if (recipientId === authUsername) {
            showError('You cannot send messages to yourself.');
            return;
        }

        if (!text && !recordedBlob) {
            showError('Type a message or attach a voice note.');
            return;
        }

        sendButton.disabled = true;

        try {
            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('recipient_id', recipientId);

            if (text) {
                formData.append('message', text);
            }

            if (recordedBlob) {
                const extension = getAudioExtension(recordedBlob.type || 'audio/webm');
                const voiceFile = new File([recordedBlob], `voice-${Date.now()}.${extension}`, {
                    type: recordedBlob.type || 'audio/webm',
                });

                formData.append('voice', voiceFile);
            }

            const response = await fetch(postUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });

            const payload = await parseJsonSafe(response);

            if (!response.ok) {
                const errors = payload?.errors ?? {};
                const firstError = Object.values(errors).flat()[0] ?? payload?.message ?? 'Message could not be sent.';
                throw new Error(firstError);
            }

            if (payload?.message) {
                renderMessage(payload.message);
                scrollMessagesToBottom();
            }

            messageInput.value = '';
            clearVoice();
            setRecipientState(`Ready to send to: ${recipientId}`, 'success');
        } catch (error) {
            showError(error instanceof Error ? error.message : 'Unexpected error while sending message.');
        } finally {
            sendButton.disabled = false;
        }
    });

    recipientInput.addEventListener('input', () => {
        recipientInput.value = recipientInput.value.toLowerCase();
    });
    findRecipientButton.addEventListener('click', findRecipient);
    startRecordButton.addEventListener('click', startRecording);
    stopRecordButton.addEventListener('click', stopRecording);
    clearVoiceButton.addEventListener('click', clearVoice);

    const initialMessages = JSON.parse(chatRoot.dataset.messages ?? '[]');
    initialMessages.forEach(renderMessage);
    scrollMessagesToBottom();

    initializeRealtime();
}
