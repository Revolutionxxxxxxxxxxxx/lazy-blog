// public/app.js

const API_BASE = '/api';

// Simple router check
const path = window.location.pathname;

// Auth State
let user = null;

import { createClient } from 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js/+esm'

let supabase;

async function initApp() {
    try {
        const config = await fetch('/config.json').then(r => r.json());
        supabase = createClient(config.SUPABASE_URL, config.SUPABASE_ANON_KEY);
        
        const { data: { session } } = await supabase.auth.getSession();
        user = session?.user;
        
        updateNav();

        supabase.auth.onAuthStateChange((_event, session) => {
            user = session?.user;
            updateNav();
        });

        // Route handling
        if (path === '/' || path === '/index.html') {
            loadHome();
        } else if (path === '/article.html') {
            loadArticle();
        } else if (path === '/editor.html') {
            if (!user) window.location.href = '/auth.html';
            loadEditor();
        } else if (path === '/auth.html') {
            initAuth();
        }

    } catch (e) {
        console.error("Setup error. Make sure config.json exists.", e);
        // Do not block UI render if just config is missing for verification, 
        // but real app needs it.
    }
}

function updateNav() {
    const nav = document.getElementById('main-nav');
    if (!nav) return;
    
    if (user) {
        nav.innerHTML = `
            <a href="/">Home</a>
            <a href="/editor.html">Write</a>
            <a href="#" id="logout-btn">Logout</a>
        `;
        document.getElementById('logout-btn').addEventListener('click', async () => {
            await supabase.auth.signOut();
            window.location.href = '/';
        });
    } else {
        nav.innerHTML = `
            <a href="/">Home</a>
            <a href="/auth.html">Login</a>
        `;
    }
}

// Framer Motion Animation Helper
function animateEntry(element) {
    if (window.Motion) {
        window.Motion.animate(element, { opacity: [0, 1], y: [20, 0] }, { duration: 0.5 });
    } else {
        // Fallback or use imported module if we decide to change index.html to standard module import
        // For CDN script in index.html, it usually exposes Motion or FramerMotion global
        // The script in index.html is: <script src="https://unpkg.com/framer-motion@10.16.4/dist/framer-motion.js"></script>
        // This UMD build exposes `Motion` global.
        if (typeof Motion !== 'undefined') {
             Motion.animate(element, { opacity: [0, 1], y: [20, 0] }, { duration: 0.5 });
        }
    }
}

// Masonry & Home
async function loadHome() {
    const container = document.getElementById('posts-grid');
    try {
        const response = await fetch(`${API_BASE}/posts`); // Clean URL
        if (!response.ok) throw new Error('Failed to fetch');
        const posts = await response.json();

        container.innerHTML = posts.map(post => `
            <div class="card" onclick="window.location.href='/article.html?id=${post.id}'">
                ${post.image_url ? `<img src="${post.image_url}" alt="${post.title}">` : ''}
                <div class="card-content">
                    <h3 class="card-title">${post.title}</h3>
                    <div class="card-meta">
                        <span>${post.profiles?.username || 'Anonymous'}</span>
                        <span>${post.views_count || 0} views</span>
                    </div>
                </div>
            </div>
        `).join('');

        // Init Masonry
        // Wait for images to load (simplified) then layout
        setTimeout(() => {
             // Check if Masonry is loaded
             if (typeof Masonry !== 'undefined') {
                 new Masonry(container, {
                     itemSelector: '.card',
                     columnWidth: '.card',
                     percentPosition: true,
                     gutter: 20
                 });
             }
        }, 100);

        // Animate
        Array.from(container.children).forEach((card, i) => {
            setTimeout(() => animateEntry(card), i * 100);
        });

    } catch (e) {
        console.warn("Could not load posts (likely no backend connection)", e);
        container.innerHTML = "<p>No posts found or database not connected.</p>";
    }
}

// Article
async function loadArticle() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) return;

    // Increment View
    fetch(`${API_BASE}/views`, { // Clean URL
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: id })
    });

    const response = await fetch(`${API_BASE}/posts?id=${id}`); // Clean URL
    const data = await response.json();
    const post = data[0];

    if (!post) {
        document.getElementById('article-content').innerHTML = 'Post not found';
        return;
    }

    const titleEl = document.getElementById('article-title');
    titleEl.innerText = post.title;
    animateEntry(titleEl);

    document.getElementById('article-meta').innerHTML = `By ${post.profiles?.username || 'Unknown'} &bull; ${post.views_count} views`;
    const bodyEl = document.getElementById('article-body');
    bodyEl.innerHTML = post.content; 
    animateEntry(bodyEl);
    
    loadComments(id);
    checkLike(id);
    
    document.getElementById('comment-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!user) return alert('Please login to comment');
        
        const content = document.getElementById('comment-input').value;
        
        // Include Access Token in header for future security upgrades
        const { data: { session } } = await supabase.auth.getSession();
        
        await fetch(`${API_BASE}/interactions?type=comment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${session?.access_token}`
            },
            body: JSON.stringify({ post_id: id, user_id: user.id, content })
        });
        document.getElementById('comment-input').value = '';
        loadComments(id);
    });
    
    document.getElementById('like-btn').addEventListener('click', async () => {
        if (!user) return alert('Please login to like');
        
        const { data: { session } } = await supabase.auth.getSession();

        await fetch(`${API_BASE}/interactions?type=like`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${session?.access_token}`
            },
            body: JSON.stringify({ post_id: id, user_id: user.id })
        });
        checkLike(id);
    });
}

async function loadComments(postId) {
    const res = await fetch(`${API_BASE}/interactions?type=comment&post_id=${postId}`);
    const comments = await res.json();
    const list = document.getElementById('comments-list');
    list.innerHTML = comments.map(c => `
        <div class="comment">
            <div class="comment-author">${c.profiles?.username || 'User'}</div>
            <div class="comment-text">${c.content}</div>
        </div>
    `).join('');
}

async function checkLike(postId) {
    if (!user) return;
    const res = await fetch(`${API_BASE}/interactions?type=like&post_id=${postId}`);
    const likes = await res.json();
    const liked = likes.some(l => l.user_id === user.id);
    const btn = document.getElementById('like-btn');
    if (liked) {
        btn.classList.add('liked');
        btn.innerText = 'Liked';
    } else {
        btn.classList.remove('liked');
        btn.innerText = 'Like';
    }
    document.getElementById('like-count').innerText = likes.length + ' Likes';
}

// Editor
async function loadEditor() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id'); 
    
    if (id) {
        const res = await fetch(`${API_BASE}/posts?id=${id}`);
        const data = await res.json();
        const post = data[0];
        document.getElementById('title').value = post.title;
        document.getElementById('content').value = post.content;
        document.getElementById('image_url').value = post.image_url || '';
    }

    document.getElementById('editor-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const title = document.getElementById('title').value;
        const content = document.getElementById('content').value;
        const image_url = document.getElementById('image_url').value;
        
        const { data: { session } } = await supabase.auth.getSession();

        const payload = { title, content, image_url, user_id: user.id };
        const headers = {
            'Content-Type': 'application/json',
             'Authorization': `Bearer ${session?.access_token}`
        };
        
        if (id) {
             await fetch(`${API_BASE}/posts?id=${id}`, {
                method: 'PUT',
                headers,
                body: JSON.stringify(payload)
             });
        } else {
             await fetch(`${API_BASE}/posts`, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload)
             });
        }
        window.location.href = '/';
    });
}

// Auth
function initAuth() {
    document.getElementById('login-btn').addEventListener('click', async () => {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const { error } = await supabase.auth.signInWithPassword({ email, password });
        if (error) alert(error.message);
        else window.location.href = '/';
    });
    
    document.getElementById('signup-btn').addEventListener('click', async () => {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const { error } = await supabase.auth.signUp({ email, password });
        if (error) alert(error.message);
        else alert('Check your email for confirmation!');
    });
}

initApp();
