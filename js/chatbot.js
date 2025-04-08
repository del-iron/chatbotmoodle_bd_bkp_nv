//Este arquivo JavaScript é responsável por capturar a entrada do usuário no 
// index.html e enviar a mensagem para o servidor usando uma requisição AJAX 
// (usualmente via fetch). Ele também recebe a resposta do 
// servidor e a exibe na interface do usuário.

// Referências aos elementos HTML
const chatButton = document.getElementById("chat-button");
const chatContainer = document.getElementById("chat-container");
const closeChat = document.getElementById("close-chat");
const userInput = document.getElementById("user-input");
const sendButton = document.getElementById("send-button");
const chatMessages = document.getElementById("chat-messages");
const chatStatus = document.getElementById("chat-status");

// Variável para controlar se o bot está digitando
let isBotTyping = false;

// Evento para abrir o chat quando o botão de chat é clicado
chatButton.addEventListener("click", () => {
    chatContainer.style.display = "flex";
    
    // Se for a primeira vez que o chat é aberto, iniciar a conversa
    if (chatMessages.children.length === 0) {
        // Mostrar indicador de digitação com animação
        showTypingIndicator();

        //invocando o chatbot.php atraves de uma requisição fetch
        setTimeout(() => {
            // Enviar requisição para iniciar a conversa no PHP
            fetch("chatbot.php", {
                //enviando o método POST
                method: "POST",
                //enviando o cabeçalho Content-Type
                headers: { "Content-Type": "application/x-www-form-urlencoded" }
            })
            //recebendo a resposta do servidor
            .then(response => response.text())
            //exibindo a mensagem do servidor
            .then(data => {
                hideTypingIndicator();
                displayBotMessageWithTypingEffect(data);
            });
            //tratando o erro
        }, 1000);
    }
});

/*Evento para fechar o chat quando o botão de fechar é clicado
closeChat.addEventListener("click", () => {
    chatContainer.style.display = "none";
});
*/

// Evento para fechar o chat quando o botão de fechar é clicado
closeChat.addEventListener("click", () => {
    chatContainer.style.display = "none";

    // Enviar requisição para encerrar a sessão no PHP
    fetch("logout.php", {
        //enviando o método POST
        method: "POST",
        //enviando o cabeçalho Content-Type
        headers: { "Content-Type": "application/x-www-form-urlencoded" }
    })
    //recebendo a resposta do servidor
    .then(response => response.text())
    .then(data => {
        alert(data); // Exibir mensagem do servidor
        location.reload(); // Recarregar a página para garantir que a sessão foi destruída
    })
    //tratando o erro 
    .catch(error => {
        alert("Erro ao encerrar a sessão!");
        console.error("Erro ao encerrar a sessão:", error);
    });
});

// Eventos para enviar a mensagem quando o botão de enviar é clicado ou a tecla Enter é pressionada
sendButton.addEventListener("click", sendMessage);
//evento para enviar a mensagem quando a tecla Enter é pressionada
userInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") sendMessage();
});

// Função para enviar a mensagem do usuário
function sendMessage() {
    let message = userInput.value.trim();
    if (message === "" || isBotTyping) return;
    addMessage(message, "user");
    userInput.value = "";

    showTypingIndicator();

    fetch("chatbot.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "message=" + encodeURIComponent(message)
    })
    .then(response => response.text())
    .then(data => {
        setTimeout(() => {
            hideTypingIndicator();
            processBotMessages(data); // Processa mensagens separadas
        }, 1000); // Atraso inicial para simular o tempo de resposta do bot
    })
    .catch(error => {
        hideTypingIndicator();
        displayBotMessageWithTypingEffect("Desculpe, ocorreu um erro ao processar sua solicitação.");
        console.error("Erro:", error);
    });
}

// Função para adicionar uma mensagem na caixa de mensagens
function addMessage(text, type) {
    //criar a div da mensagem
    let msg = document.createElement("div");
    //adicionar a classe da mensagem
    msg.classList.add("message", type);
    
    //verificar o tipo da mensagem
    if (type === "bot") {
        // Remover a imagem do bot
        // let img = document.createElement("img");
        // img.src = "https://i.imgur.com/6RK7NQp.png";
        // msg.appendChild(img);
    }
    //criar o span
    let span = document.createElement("span");
    //adicionar o texto
    span.textContent = text;
    //adicionar o span na mensagem
    msg.appendChild(span);
    //adicionar a mensagem na caixa de mensagens
    chatMessages.appendChild(msg);
    //rolar a caixa de mensagens para o final
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Mostrar indicador de digitação com animação de pontos
function showTypingIndicator() {
    isBotTyping = true;
    chatStatus.textContent = "Digitando";
    
    // Criar animação dos pontos (...) no indicador de digitação
    let dots = 0;
    const typingInterval = setInterval(() => {
        if (!isBotTyping) {
            clearInterval(typingInterval);
            return;
        }
        // Alternar entre 0, 1, 2 e 3 pontos
        dots = (dots + 1) % 4;
        let dotsText = "";
        for (let i = 0; i < dots; i++) {
            dotsText += ".";
        }
        // Atualizar o texto do indicador de digitação
        chatStatus.textContent = "Digitando" + dotsText;
    }, 500);
}

// Função para esconder o indicador de digitação
function hideTypingIndicator() {
    isBotTyping = false;
    chatStatus.textContent = "Online agora";
}

// Função para exibir a mensagem do bot com efeito de digitação
function displayBotMessageWithTypingEffect(text) {
    let msg = document.createElement("div");
    msg.classList.add("message", "bot");

    let span = document.createElement("span");
    span.innerHTML = ""; // Inicializa o conteúdo vazio
    msg.appendChild(span);
    chatMessages.appendChild(msg);

    let i = 0;

    function typeCharacter() {
        if (i < text.length) {
            span.innerHTML += text.charAt(i); // Adiciona caractere por caractere
            i++;
            chatMessages.scrollTop = chatMessages.scrollHeight; // Rola para o final

            let typingSpeed = Math.random() * 50 + 30; // Velocidade de digitação
            if (['.', '!', '?', ',', ':'].includes(text.charAt(i - 1))) {
                typingSpeed += 300; // Pausa maior após pontuações
            }
            setTimeout(typeCharacter, typingSpeed);
        } else {
            isBotTyping = false; // Finaliza o estado de digitação
        }
    }
    typeCharacter();
}

// Ajuste para processar mensagens separadas por quebras de linha
function processBotMessages(data) {
    const messages = data.split("\n"); // Divide as mensagens por quebra de linha
    let delay = 0;

    messages.forEach((message) => {
        if (message.trim() !== "") {
            setTimeout(() => {
                displayBotMessageWithTypingEffect(message.trim());
            }, delay);
            delay += message.length * 50 + 500; // Ajusta o atraso com base no tamanho da mensagem
        }
    });
}