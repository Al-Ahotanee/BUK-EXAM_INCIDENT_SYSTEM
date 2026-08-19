const API='./api.php';
let csrf='';
const $=s=>document.querySelector(s);
const show=(id,on=true)=>{const el=$(id);if(el)el.classList.toggle('hidden',!on)};
const redirectUser=()=>{window.location.href='workspace.php'};
async function apiCall(action,data={}){return axios.post(`${API}?action=${action}`,data,{headers:{'X-Csrf-Token':csrf}})}
function openLogin(){show('#landing',false);show('#login',true);window.scrollTo(0,0);setTimeout(()=>$('#login-email')?.focus(),80)}
function closeLogin(){show('#login',false);show('#landing',true);window.scrollTo(0,0)}
function toggleMenu(){document.querySelector('.landing-nav')?.classList.toggle('mobile-open')}
function initNav(){const nav=$('.landing-nav');window.addEventListener('scroll',()=>nav?.classList.toggle('scrolled',window.scrollY>28));document.querySelectorAll('[data-scroll]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();document.querySelector(a.dataset.scroll)?.scrollIntoView({behavior:'smooth'});nav?.classList.remove('mobile-open')}));}
async function initAuth(){try{const r=await axios.post(`${API}?action=check_auth`);csrf=r.data.csrf_token||'';if(r.data.isAuthenticated)redirectUser()}catch(e){csrf=''}}
async function submitLogin(e){e.preventDefault();const email=$('#login-email').value.trim(),password=$('#login-password').value;const button=$('#login-submit'),error=$('#login-error');error.classList.add('hidden');button.disabled=true;button.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Signing in…';try{const res=await apiCall('login',{email,password});if(res.data.success){redirectUser();return}throw new Error(res.data.message||'Unable to sign in')}catch(e){error.textContent=e.response?.data?.message||e.message||'Authentication service is unavailable. Please try again.';error.classList.remove('hidden');try{const r=await axios.post(`${API}?action=check_auth`);csrf=r.data.csrf_token||csrf}catch(_){}}finally{button.disabled=false;button.innerHTML='<i class="fas fa-arrow-right-to-bracket"></i> Sign in to workspace'}}
document.addEventListener('DOMContentLoaded',()=>{initNav();initAuth();$('#open-login')?.addEventListener('click',openLogin);$('#hero-login')?.addEventListener('click',openLogin);$('#cta-login')?.addEventListener('click',openLogin);$('#close-login')?.addEventListener('click',closeLogin);$('#mobile-menu')?.addEventListener('click',toggleMenu);$('#login-form')?.addEventListener('submit',submitLogin);document.querySelectorAll('[data-close-login]').forEach(x=>x.addEventListener('click',closeLogin))});
