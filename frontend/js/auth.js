// Atualiza o topo da página e o formulário de publicação conforme o login
function checkAuth() {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('usuario') || 'null');
    const userMenu = document.getElementById('userMenu');
    const createPostSection = document.getElementById('createPostSection');

    // Logado: mostra @username (abre o perfil) e o botão Sair
    if (token && user) {
        userMenu.innerHTML = `
            <span class="link" onclick="openProfile(${user.id_usuario})">@${user.username}</span>
            <button class="btn" onclick="logout()">Sair</button>
        `;
        // Mostra o formulário só se o usuário for "criador"
        if (user.tipo_perfil === 'criador') {
            createPostSection.classList.remove('hidden');
        } else {
            createPostSection.classList.add('hidden');
        }
    } else {    
        // Deslogado: mostra o botão Entrar e esconde o formulário de publicação
        userMenu.innerHTML = `<button class="btn primary" onclick="openAuthModal()">Entrar</button>`;
        createPostSection.classList.add('hidden');
    }
}

// Abre/fecha o modal de login e cadastro
function openAuthModal() {
    document.getElementById('authModal').classList.remove('hidden');
}

function closeAuthModal() {
    document.getElementById('authModal').classList.add('hidden');
}

// Alterna entre as abas Login e Cadastrar
function switchTab(tab) {
    const isLogin = tab === 'login';
    document.getElementById('loginForm').classList.toggle('hidden', !isLogin);
    document.getElementById('registerForm').classList.toggle('hidden', isLogin);
    document.getElementById('tabLogin').classList.toggle('active', isLogin);
    document.getElementById('tabRegister').classList.toggle('active', !isLogin);
}

// Sai: apaga token e dados salvos, atualiza o topo e volta ao feed
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('usuario');
    checkAuth();
    showFeed();
}

// Envio do login: chama /login e guarda token e usuário no navegador
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorDiv = document.getElementById('loginError');
    errorDiv.classList.add('hidden');

    try {
        const res = await apiFetch('/login', {
            method: 'POST',
            body: JSON.stringify({
                email: document.getElementById('loginEmail').value,
                senha: document.getElementById('loginSenha').value
            })
        });

        // Guarda token e usuário no localStorage
        localStorage.setItem('token', res.token);
        localStorage.setItem('usuario', JSON.stringify(res.usuario));
        closeAuthModal();
        checkAuth();
        showFeed();
    } catch (err) {
        errorDiv.textContent = err.message;
        errorDiv.classList.remove('hidden');
    }
});

// Envio do cadastro (FormData por causa da foto)
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorDiv = document.getElementById('regError');
    errorDiv.classList.add('hidden');

    const formData = new FormData();
    formData.append('nome', document.getElementById('regNome').value);
    formData.append('username', document.getElementById('regUsername').value);
    formData.append('email', document.getElementById('regEmail').value);
    formData.append('senha', document.getElementById('regSenha').value);
    
    const foto = document.getElementById('regFoto').files[0];
    if (foto) formData.append('foto', foto);

    try {
        // Chama /cadastro; se der certo, volta para a aba de login
        await apiFetch('/cadastro', { method: 'POST', body: formData });
        alert('Cadastro realizado com sucesso! Faça login.');
        switchTab('login');
    } catch (err) {
        errorDiv.textContent = err.message;
        errorDiv.classList.remove('hidden');
    }
});
