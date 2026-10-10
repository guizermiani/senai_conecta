// Guarda qual perfil está aberto (null = estamos no feed)
let perfilAbertoId = null;

// Ao carregar a página: ajusta o topo (login) e carrega o feed
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadFeed();
});

// Busca as publicações na API e desenha o feed
async function loadFeed() {
    const feed = document.getElementById('feed');
    try {
        const posts = await apiFetch('/publicacoes');
        feed.innerHTML = posts.map(post => renderPost(post)).join('');
    } catch (err) {
        feed.innerHTML = `<p class="error">Erro ao carregar o feed.</p>`;
    }
}

// Gera o HTML de uma publicação (usado no feed e no perfil)
function renderPost(post) {
    // isOwner: o logado é o autor (mostra Excluir) | isLiked: o logado já curtiu
    const user = JSON.parse(localStorage.getItem('usuario') || 'null');
    const isOwner = user && user.id_usuario === post.id_usuario;
    const isLiked = post.curtido_pelo_usuario == 1;

    // HTML da publicação: autor (clicável), texto, imagem, curtidas e data
    return `
        <div class="post-card" id="post-${post.id_publicacao}">
            <div class="post-header">
                <div>
                    <span class="post-author link" onclick="openProfile(${post.id_usuario})">${escapeHtml(post.nome)}</span>
                    <span class="link" style="color:#777" onclick="openProfile(${post.id_usuario})">@${escapeHtml(post.username)}</span>
                </div>
                ${isOwner ? `<button class="btn" onclick="deletePost(${post.id_publicacao})">Excluir</button>` : ''}
            </div>
            <p>${escapeHtml(post.texto)}</p>
            ${post.imagem ? `<img src="${API_BASE_URL}/uploads/${post.imagem}" class="post-image">` : ''}
            <div class="post-actions">
                <button class="like-btn ${isLiked ? 'liked' : ''}" onclick="toggleLike(${post.id_publicacao})">
                    ${isLiked ? '♥' : '♡'} ${post.total_curtidas}
                </button>
                <small style="color:#888">${new Date(post.datahora_publicacao).toLocaleString('pt-BR')}</small>
            </div>
        </div>
    `;
}

// Curtir/descurtir; sem login abre o modal de login
async function toggleLike(idPublicacao) {
    if (!localStorage.getItem('token')) {
        openAuthModal();
        return;
    }

    try {
        await apiFetch('/curtir', {
            method: 'POST',
            body: JSON.stringify({ id_publicacao: idPublicacao })
        });
        atualizarTela();
    } catch (err) {
        alert(err.message);
    }
}

// Exclui a publicação (pede confirmação)
async function deletePost(idPublicacao) {
    if (!confirm('Deseja realmente excluir esta publicação?')) return;

    try {
        await apiFetch(`/publicacoes/${idPublicacao}`, { method: 'DELETE' });
        atualizarTela();
    } catch (err) {
        alert(err.message);
    }
}

// Envio do formulário de nova publicação (texto + imagem opcional)
document.getElementById('postForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('texto', document.getElementById('postText').value);
    
    const img = document.getElementById('postImage').files[0];
    if (img) formData.append('imagem', img);

    try {
        await apiFetch('/publicacoes', { method: 'POST', body: formData });
        document.getElementById('postForm').reset();
        loadFeed();
    } catch (err) {
        alert(err.message);
    }
});

// Escapa caracteres especiais do texto (evita injeção de HTML/XSS)
function escapeHtml(text) {
    return text.replace(/[&<>"']/g, match => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[match]));
}

// ===================== PERFIL =====================

// Monta a URL da foto. Se não houver foto, usa o avatar padrão.
function fotoUrl(foto) {
    return `${API_BASE_URL}/uploads/${foto || 'avatar.png'}`;
}

// Recarrega o que está na tela: o perfil aberto ou o feed
function atualizarTela() {
    if (perfilAbertoId !== null) openProfile(perfilAbertoId);
    else loadFeed();
}

// Volta para o feed
function showFeed() {
    perfilAbertoId = null;
    document.getElementById('profileView').classList.add('hidden');
    document.getElementById('feedView').classList.remove('hidden');
    loadFeed();
}

// Abre a aba de perfil do usuário com esse id
async function openProfile(idUsuario) {
    perfilAbertoId = idUsuario;
    const content = document.getElementById('profileContent');

    // Esconde o feed e mostra a aba de perfil
    document.getElementById('feedView').classList.add('hidden');
    document.getElementById('profileView').classList.remove('hidden');

    try {
        // Chama GET /usuarios/{id} (o apiFetch já manda o token, se existir)
        const perfil = await apiFetch(`/usuarios/${idUsuario}`);
        content.innerHTML = renderProfile(perfil);
    } catch (err) {
        content.innerHTML = `
            <button class="btn" onclick="showFeed()">← Voltar ao feed</button>
            <p class="error-msg">${escapeHtml(err.message)}</p>`;
    }
}

// Transforma o JSON do perfil em HTML
function renderProfile(perfil) {
    // Reaproveita o renderPost para cada publicação do usuário
    const posts = perfil.publicacoes.length
        ? perfil.publicacoes.map(p => renderPost(p)).join('')
        : '<p class="empty">Nenhuma publicação ainda.</p>';

    return `
        <button class="btn" onclick="showFeed()">← Voltar ao feed</button>

        <div class="card profile-card">
            <!-- onerror: se a imagem não existir, troca pelo avatar padrão -->
            <img class="profile-avatar" src="${fotoUrl(perfil.foto)}"
                 onerror="this.onerror=null; this.src='${fotoUrl('avatar.png')}'">
            <div class="profile-info">
                <h2>${escapeHtml(perfil.nome)}</h2>
                <span class="profile-username">@${escapeHtml(perfil.username)}</span>
                <div class="profile-stats">
                    <div><strong>${perfil.total_publicacoes}</strong><span>publicações</span></div>
                    <div><strong>${perfil.total_curtidas_recebidas}</strong><span>curtidas recebidas</span></div>
                </div>
            </div>
        </div>

        <h3>Publicações</h3>
        ${posts}
    `;
}

// ===================== PESQUISA DE USUÁRIOS =====================
// Código NOVO: só acrescenta, não altera nada do que já existe.
// Usa o que o projeto já tem: apiFetch, escapeHtml, API_BASE_URL e openProfile.
(function () {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;                          // sem barra de pesquisa, não faz nada

    searchInput.setAttribute('autocomplete', 'off');   // tira as sugestões do navegador

    // Cria a caixa de resultados dentro da .search-box (assim não precisa mexer no HTML)
    const searchResults = document.createElement('div');
    searchResults.className = 'search-results hidden';
    searchInput.parentElement.appendChild(searchResults);

    let buscaTimer = null;   // guarda o "relógio" do debounce

    // Monta a URL da foto (se não tiver foto, usa o avatar padrão)
    function foto(nomeArquivo) {
        return `${API_BASE_URL}/uploads/${nomeArquivo || 'avatar.png'}`;
    }

    // Chama GET /usuarios?busca=texto
    function buscarUsuarios(termo) {
        // encodeURIComponent protege caracteres especiais na URL (espaço, &, @...)
        return apiFetch(`/usuarios?busca=${encodeURIComponent(termo)}`);
    }

    // Esconde e limpa a lista de resultados
    function fecharResultados() {
        searchResults.classList.add('hidden');
        searchResults.innerHTML = '';
    }

    // Desenha a lista de resultados embaixo da barra
    function mostrarResultados(usuarios) {
        if (usuarios.length === 0) {
            searchResults.innerHTML = '<div class="search-empty">Nenhum usuário encontrado.</div>';
        } else {
            searchResults.innerHTML = usuarios.map(u => `
                <div class="search-item" data-id="${u.id_usuario}">
                    <img src="${foto(u.foto)}"
                         onerror="this.onerror=null; this.src='${foto('avatar.png')}'">
                    <div>
                        <strong>${escapeHtml(u.nome)}</strong>
                        <small>@${escapeHtml(u.username)}</small>
                    </div>
                </div>
            `).join('');
        }
        searchResults.classList.remove('hidden');
    }

    // Limpa a busca e abre a aba de perfil (a mesma função que você já tem)
    function abrirPerfil(idUsuario) {
        fecharResultados();
        searchInput.value = '';
        openProfile(Number(idUsuario));
    }

    // Busca na API e mostra os resultados
    async function pesquisar(termo) {
        try {
            const usuarios = await buscarUsuarios(termo);
            // Se a pessoa mudou o texto enquanto a resposta vinha, ignora esta resposta
            if (searchInput.value.trim() !== termo) return;
            mostrarResultados(usuarios);
        } catch (err) {
            searchResults.innerHTML = '<div class="search-empty">Erro ao pesquisar.</div>';
            searchResults.classList.remove('hidden');
        }
    }

    // A cada tecla: espera 300ms sem digitar antes de buscar (evita 1 requisição por letra)
    searchInput.addEventListener('input', () => {
        clearTimeout(buscaTimer);
        const termo = searchInput.value.trim();
        if (termo === '') {
            fecharResultados();
            return;
        }
        buscaTimer = setTimeout(() => pesquisar(termo), 300);
    });

    // Enter: abre direto o primeiro usuário encontrado
    searchInput.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const termo = searchInput.value.trim();
        if (termo === '') return;

        clearTimeout(buscaTimer);
        try {
            const usuarios = await buscarUsuarios(termo);
            if (usuarios.length > 0) abrirPerfil(usuarios[0].id_usuario);
            else mostrarResultados(usuarios);   // mostra "Nenhum usuário encontrado."
        } catch (err) {
            alert(err.message);
        }
    });

    // Clicar em um resultado abre o perfil
    searchResults.addEventListener('click', (e) => {
        const item = e.target.closest('.search-item');
        if (item) abrirPerfil(item.dataset.id);
    });

    // Clicar fora da barra de pesquisa fecha a lista
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) fecharResultados();
    });
})();