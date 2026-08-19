<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - BUK EIMS</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- React & Babel -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- Chart.js (SRS 8.4) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0B5C8E',
                        'primary-dark': '#06385B',
                        'primary-light': '#0875B4',
                        accent: '#C6A15B',
                        'accent-light': '#E4C777'
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        display: ['Manrope', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    },
                    boxShadow: {
                        soft: '0 2px 10px -2px rgba(15,36,68,0.08), 0 1px 3px -1px rgba(15,36,68,0.06)',
                        card: '0 8px 24px -8px rgba(15,36,68,0.14)'
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom Scrollbar for Timeline */
        .timeline-scroll::-webkit-scrollbar { width: 6px; }
        .timeline-scroll::-webkit-scrollbar-thumb { background-color: #CBD5E1; border-radius: 4px; }
    </style>
    <style>
        h1,h2,h3,h4,h5,h6,.font-display { font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; letter-spacing: -0.01em; }
        ::selection { background: #C6A15B; color: #fff; }
        body { -webkit-font-smoothing: antialiased; }
    </style>
</head>
<body class="bg-gray-50 text-gray-700 font-sans">
<div id="root"></div>

<script type="text/babel">
    const API = './api.php';
    const { useState, useEffect, useRef } = React;

    // --- Reusable UI Components ---
    const Modal = ({ title, onClose, children, onSubmit, submitText = "Save", loading = false, width = "max-w-2xl" }) => (
        <div className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className={`bg-white rounded-xl shadow-2xl w-full ${width} overflow-hidden flex flex-col max-h-[90vh]`}>
                <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 className="font-bold text-lg text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-red-500"><i className="fas fa-times"></i></button>
                </div>
                <div className="p-6 overflow-y-auto flex-1">
                    {children}
                </div>
                <div className="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button onClick={onClose} className="px-4 py-2 text-sm font-bold text-gray-600 hover:text-gray-900">Cancel</button>
                    {onSubmit && (
                        <button onClick={onSubmit} disabled={loading} className="px-6 py-2 bg-primary text-white text-sm font-bold rounded-lg shadow-soft hover:shadow-card hover:bg-accent-light transition-all disabled:opacity-50 disabled:hover:translate-y-0 hover:-translate-y-0.5">
                            {loading ? <i className="fas fa-spinner fa-spin"></i> : submitText}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );

    const StatusBadge = ({ status }) => {
        const getStyle = (s) => {
            switch(s?.toLowerCase()) {
                case 'pending': return 'bg-gray-100 text-gray-700 border-gray-200';
                case 'reported': return 'bg-gray-100 text-gray-700 border-gray-200';
                case 'under_review': return 'bg-blue-100 text-blue-800 border-blue-200';
                case 'case_opened': return 'bg-blue-100 text-blue-800 border-blue-200';
                case 'investigation': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
                case 'hearing': return 'bg-orange-100 text-orange-800 border-orange-200';
                case 'resolved': return 'bg-green-100 text-green-800 border-green-200';
                case 'dismissed': return 'bg-gray-200 text-gray-600 border-gray-300';
                default: return 'bg-gray-100 text-gray-700 border-gray-200';
            }
        };
        return (
            <span className={`px-2.5 py-1 rounded-md text-[10px] font-bold border uppercase tracking-wider ${getStyle(status)}`}>
                {status?.replace('_', ' ')}
            </span>
        );
    };

    function AdminApp() {
        const [user, setUser] = useState(null);
        const [csrf, setCsrf] = useState('');
        const [view, setView] = useState('dashboard');
        const [lookups, setLookups] = useState({ departments: [], faculties: [], courses: [], committee_members: [] });
        
        useEffect(() => {
            axios.post(`${API}?action=check_auth`).then(res => {
                if (res.data.isAuthenticated && res.data.user.role === 'admin') {
                    setUser(res.data.user);
                    setCsrf(res.data.csrf_token);
                    fetchLookups();
                } else window.location.href = 'index.php';
            });
        }, []);

        const fetchLookups = () => apiCall('get_lookups').then(r => setLookups(r.data.data));
        const apiCall = async (action, data = {}) => axios.post(`${API}?action=${action}`, data, { headers: { 'X-Csrf-Token': csrf } });
        const logout = () => apiCall('logout').then(() => window.location.href = 'index.php');

        // --- VIEW COMPONENTS ---

        const DashboardView = () => {
            const [stats, setStats] = useState({ users: 0, incidents: 0, active_cases: 0, resolved_cases: 0, recent: [] });
            const [loading, setLoading] = useState(true);

            useEffect(() => {
                setLoading(true);
                apiCall('get_admin_dashboard').then(res => setStats(res.data.data)).catch(() => {}).finally(() => setLoading(false));
            }, []);

            const StatCard = ({ icon, iconBg, iconColor, value, label }) => (
                <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center gap-4">
                    <div className={`w-12 h-12 rounded-full ${iconBg} ${iconColor} flex items-center justify-center text-xl shrink-0`}><i className={`fas ${icon}`}></i></div>
                    {loading ? (
                        <div className="flex-1 min-w-0">
                            <div className="h-7 w-16 bg-gray-100 rounded animate-pulse mb-2"></div>
                            <div className="h-3 w-24 bg-gray-100 rounded animate-pulse"></div>
                        </div>
                    ) : (
                        <div><div className="text-3xl font-extrabold text-gray-900">{value || 0}</div><div className="text-xs text-gray-500 uppercase font-bold tracking-wide">{label}</div></div>
                    )}
                </div>
            );

            return (
                <div className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <StatCard icon="fa-users" iconBg="bg-blue-50" iconColor="text-blue-600" value={stats.users} label="Total Users" />
                        <StatCard icon="fa-folder-open" iconBg="bg-red-50" iconColor="text-red-600" value={stats.incidents} label="Total Incidents" />
                        <StatCard icon="fa-gavel" iconBg="bg-yellow-50" iconColor="text-yellow-600" value={stats.active_cases} label="Active Cases" />
                        <StatCard icon="fa-check-circle" iconBg="bg-green-50" iconColor="text-green-600" value={stats.resolved_cases} label="Resolved Cases" />
                    </div>
                </div>
            );
        };

        const UsersView = () => {
            const [users, setUsers] = useState([]);
            const [showModal, setShowModal] = useState(false);
            const [editingUser, setEditingUser] = useState(null);
            const [msg, setMsg] = useState({ text: '', type: '' });
            const [loading, setLoading] = useState(true);
            const [form, setForm] = useState({ id:'', staff_id:'', full_name:'', email:'', password:'', role:'invigilator', faculty_id:'', department_id:'' });
            
            const fetchUsers = () => { setLoading(true); return apiCall('get_users').then(res => setUsers(res.data.data)).finally(() => setLoading(false)); };
            useEffect(() => { fetchUsers(); }, []);

            const handleEdit = (user) => {
                setEditingUser(user);
                setForm({ ...user, password: '' });
                setShowModal(true);
            };

            const handleCreate = () => {
                setEditingUser(null);
                setForm({ id:'', staff_id:'', full_name:'', email:'', password:'', role:'invigilator', faculty_id:'', department_id:'' });
                setShowModal(true);
            };

            const submitForm = async (e) => {
                e.preventDefault(); setMsg({text:'', type:''});
                const action = editingUser ? 'update_user' : 'create_user';
                try {
                    await apiCall(action, form);
                    fetchUsers(); setShowModal(false);
                } catch(err) { setMsg({text: err.response?.data?.message || 'Error saving user', type: 'error'}); }
            };

            const toggleStatus = async (id, currentStatus) => {
                if(!confirm(`Are you sure you want to ${currentStatus ? 'disable' : 'enable'} this user?`)) return;
                try {
                    await apiCall('toggle_user', { id, is_active: !currentStatus ? 1 : 0 });
                    fetchUsers();
                } catch(err) { alert('Action failed'); }
            };

            const deleteUser = async (id) => {
                if(!confirm('Delete this user permanently? This action cannot be undone and will fail if the user has associated records.')) return;
                try {
                    await apiCall('delete_user', { id });
                    fetchUsers();
                } catch(err) { alert(err.response?.data?.message || 'Deletion failed. User may have associated records.'); }
            };

            return (
                <div className="space-y-6">
                    <div className="flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <div className="relative">
                            <i className="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input className="pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-md focus:border-accent focus:ring-1 focus:ring-accent outline-none text-sm w-64" placeholder="Search staff..."/>
                        </div>
                        <button onClick={handleCreate} className="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold shadow-soft hover:shadow-card hover:bg-accent-light transition-all hover:-translate-y-0.5 flex items-center gap-2">
                            <i className="fas fa-plus"></i> Add New User
                        </button>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50/80 text-[11px] uppercase tracking-wider text-gray-500 border-b border-gray-200 sticky top-0">
                                <tr>
                                    <th className="p-4 font-bold">Staff ID & Name</th>
                                    <th className="p-4 font-bold">Contact</th>
                                    <th className="p-4 font-bold">Role & Dept</th>
                                    <th className="p-4 font-bold text-center">Status</th>
                                    <th className="p-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? (
                                    Array.from({ length: 5 }).map((_, i) => (
                                        <tr key={`sk-${i}`}>
                                            <td className="p-4"><div className="h-4 w-32 bg-gray-100 rounded animate-pulse mb-2"></div><div className="h-3 w-20 bg-gray-100 rounded animate-pulse"></div></td>
                                            <td className="p-4"><div className="h-4 w-40 bg-gray-100 rounded animate-pulse"></div></td>
                                            <td className="p-4"><div className="h-4 w-24 bg-gray-100 rounded animate-pulse mb-2"></div><div className="h-3 w-16 bg-gray-100 rounded animate-pulse"></div></td>
                                            <td className="p-4 text-center"><div className="h-5 w-16 bg-gray-100 rounded animate-pulse mx-auto"></div></td>
                                            <td className="p-4 text-right"><div className="h-5 w-20 bg-gray-100 rounded animate-pulse ml-auto"></div></td>
                                        </tr>
                                    ))
                                ) : users.length === 0 ? (
                                    <tr><td colSpan="5" className="p-8 text-center text-gray-400">No users found.</td></tr>
                                ) : users.map(u => <tr key={u.id} className="hover:bg-gray-50 transition">
                                    <td className="p-4">
                                        <div className="font-bold text-gray-900">{u.full_name}</div>
                                        <div className="text-xs text-gray-500 font-mono mt-0.5">{u.staff_id}</div>
                                    </td>
                                    <td className="p-4 text-sm text-gray-600">{u.email}</td>
                                    <td className="p-4">
                                        <div className="text-sm font-bold text-primary capitalize">{u.role.replace('_', ' ')}</div>
                                        <div className="text-xs text-gray-500 truncate max-w-[200px]">{u.dept_name || 'System Wide'}</div>
                                    </td>
                                    <td className="p-4 text-center">
                                        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold ${u.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            <span className={`w-1.5 h-1.5 rounded-full ${u.is_active ? 'bg-green-500' : 'bg-red-500'}`}></span>
                                            {u.is_active ? 'Active' : 'Disabled'}
                                        </span>
                                    </td>
                                    <td className="p-4 text-right space-x-2">
                                        <button onClick={() => handleEdit(u)} className="text-blue-500 hover:bg-blue-50 p-2 rounded transition" title="Edit"><i className="fas fa-edit"></i></button>
                                        <button onClick={() => toggleStatus(u.id, u.is_active)} className={`${u.is_active ? 'text-yellow-600 hover:bg-yellow-50' : 'text-green-600 hover:bg-green-50'} p-2 rounded transition`} title="Toggle Access"><i className={`fas ${u.is_active ? 'fa-ban' : 'fa-check'}`}></i></button>
                                        <button onClick={() => deleteUser(u.id)} className="text-red-500 hover:bg-red-50 p-2 rounded transition" title="Delete"><i className="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>)}
                            </tbody>
                        </table>
                    </div>

                    {showModal && (
                        <Modal title={editingUser ? "Edit User" : "Add System Personnel"} width="max-w-2xl" onClose={() => setShowModal(false)} submitText="Save User" onSubmit={submitForm}>
                            {msg.text && <div className={`p-3 rounded mb-4 text-sm border ${msg.type === 'error' ? 'bg-red-50 text-red-600 border-red-200' : 'bg-green-50 text-green-600 border-green-200'}`}>{msg.text}</div>}
                            <form id="userForm" className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div><label className="block text-xs font-bold text-gray-700 mb-1">Staff ID *</label><input className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none" required value={form.staff_id} onChange={e=>setForm({...form, staff_id:e.target.value})}/></div>
                                    <div><label className="block text-xs font-bold text-gray-700 mb-1">Full Name *</label><input className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none" required value={form.full_name} onChange={e=>setForm({...form, full_name:e.target.value})}/></div>
                                </div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Email Address *</label><input type="email" className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none" required value={form.email} onChange={e=>setForm({...form, email:e.target.value})}/></div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">System Role *</label>
                                    <select className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none bg-white" required value={form.role} onChange={e=>setForm({...form, role:e.target.value})}>
                                        <option value="invigilator">Invigilator</option><option value="exam_officer">Exam Officer</option><option value="hod">HOD / Dean</option><option value="committee">Committee Member</option><option value="admin">Administrator</option>
                                    </select>
                                </div>
                                {['hod','dean'].includes(form.role) && (
                                    <div><label className="block text-xs font-bold text-gray-700 mb-1">Faculty (For Deans)</label>
                                        <select className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none bg-white" value={form.faculty_id} onChange={e=>setForm({...form, faculty_id:e.target.value})}>
                                            <option value="">Select Faculty...</option>
                                            {lookups.faculties.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                                        </select>
                                    </div>
                                )}
                                {['invigilator','hod','committee'].includes(form.role) && (
                                    <div><label className="block text-xs font-bold text-gray-700 mb-1">Department</label>
                                        <select className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none bg-white" value={form.department_id} onChange={e=>setForm({...form, department_id:e.target.value})}>
                                            <option value="">Select Department...</option>
                                            {lookups.departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                        </select>
                                    </div>
                                )}
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">{editingUser ? "New Password (leave blank to keep current)" : "Initial Password *"}</label>
                                    <input type="password" minLength="8" className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none" required={!editingUser} onChange={e=>setForm({...form, password:e.target.value})}/>
                                </div>
                            </form>
                        </Modal>
                    )}
                </div>
            );
        };

        const IncidentsView = () => {
            const [incidents, setIncidents] = useState([]);
            const [loading, setLoading] = useState(false);
            
            useEffect(() => { 
                setLoading(true); 
                apiCall('get_incidents').then(res => setIncidents(res.data.data)).finally(() => setLoading(false)); 
            }, []);

            return (
                <div className="space-y-6">
                    <div className="flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <h3 className="font-bold text-lg text-gray-800">All Incident Reports</h3>
                    </div>
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50/80 text-[11px] uppercase tracking-wider text-gray-500 border-b border-gray-200 sticky top-0">
                                <tr><th className="p-4 font-bold">Ref & Date</th><th className="p-4 font-bold">Student Info</th><th className="p-4 font-bold">Offence Details</th><th className="p-4 font-bold text-center">Status</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? <tr><td colSpan="4" className="p-8 text-center text-gray-400">Loading...</td></tr> : incidents.length === 0 ? <tr><td colSpan="4" className="p-8 text-center text-gray-400">No incidents found.</td></tr> : incidents.map(inc => (
                                    <tr key={inc.id} className="hover:bg-gray-50 transition">
                                        <td className="p-4">
                                            <div className="font-bold text-primary">{inc.reference_no}</div>
                                            <div className="text-xs text-gray-500 mt-0.5">{inc.exam_date}</div>
                                        </td>
                                        <td className="p-4">
                                            <div className="font-bold text-gray-900">{inc.student_name}</div>
                                            <div className="text-xs text-gray-500 font-mono mt-0.5">{inc.student_matric}</div>
                                        </td>
                                        <td className="p-4">
                                            <div className="text-sm font-bold text-gray-700 capitalize">{inc.offence_type.replace('_', ' ')}</div>
                                            <div className="text-xs text-gray-500">{inc.course_code}</div>
                                        </td>
                                        <td className="p-4 text-center">
                                            <StatusBadge status={inc.status} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            );
        };

        const CasesView = () => {
            const [cases, setCases] = useState([]);
            const [loading, setLoading] = useState(false);
            
            // 360 View State
            const [view360, setView360] = useState(null);
            const [loading360, setLoading360] = useState(false);

            useEffect(() => { 
                setLoading(true); 
                apiCall('get_cases').then(res => setCases(res.data.data)).finally(() => setLoading(false)); 
            }, []);

            const open360View = async (caseId) => {
                setLoading360(true);
                try {
                    const res = await apiCall('get_case_details', { case_id: caseId });
                    setView360(res.data.data);
                } catch(e) {
                    alert("Failed to load case details.");
                } finally { setLoading360(false); }
            };

            return (
                <div className="space-y-6">
                    <div className="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex justify-between items-center">
                        <h3 className="font-bold text-lg text-gray-800">All System Cases</h3>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50/80 text-[11px] uppercase tracking-wider text-gray-500 border-b border-gray-200 sticky top-0">
                                <tr><th className="p-4">Case / Incident</th><th className="p-4">Student</th><th className="p-4">Current Stage</th><th className="p-4">Committee</th><th className="p-4 text-right">Actions</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? <tr><td colSpan="5" className="p-8 text-center text-gray-400">Loading cases...</td></tr> : cases.length === 0 ? <tr><td colSpan="5" className="p-8 text-center text-gray-400">No cases found.</td></tr> : cases.map(c => (
                                    <tr key={c.id} className="hover:bg-gray-50">
                                        <td className="p-4"><div className="font-bold text-primary">{c.case_no}</div><div className="text-xs text-gray-500 font-mono">INC: {c.reference_no}</div></td>
                                        <td className="p-4"><div className="font-bold text-gray-900">{c.student_name}</div><div className="text-xs text-gray-500">{c.student_matric}</div></td>
                                        <td className="p-4"><StatusBadge status={c.stage} /></td>
                                        <td className="p-4">
                                            {c.assigned_committee ? (
                                                <div className="text-xs font-bold text-gray-800">{lookups.committee_members.find(m => m.id == c.assigned_committee)?.full_name || 'Assigned'}</div>
                                            ) : (
                                                <span className="text-xs text-gray-400 italic">Unassigned</span>
                                            )}
                                        </td>
                                        <td className="p-4 text-right">
                                            {/* 360 View Button for Admin */}
                                            <button onClick={() => open360View(c.id)} className="bg-primary hover:bg-accent-light text-white px-4 py-1.5 rounded-lg text-xs font-bold transition-all shadow-soft hover:shadow-card hover:-translate-y-0.5" title="Full 360° Case View">
                                                {loading360 ? <i className="fas fa-spinner fa-spin"></i> : <><i className="fas fa-eye mr-1"></i> View Case Dossier</>}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* --- THE 360° CASE VIEWER MODAL (Admin Version) --- */}
                    {view360 && (
                        <Modal title={`360° Case Dossier: ${view360.case.case_no}`} width="max-w-5xl" onClose={() => setView360(null)}>
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 relative">
                                {/* Left Column: Core Data */}
                                <div className="lg:col-span-2 space-y-6">
                                    {/* Student & Offense Header */}
                                    <div className="bg-primary text-white p-6 rounded-xl shadow-inner relative overflow-hidden">
                                        <div className="absolute -right-4 -top-4 opacity-10 text-9xl"><i className="fas fa-balance-scale"></i></div>
                                        <div className="relative z-10 flex justify-between items-start">
                                            <div>
                                                <h2 className="text-2xl font-extrabold">{view360.incident.student_name}</h2>
                                                <p className="text-blue-200 font-mono text-sm tracking-widest mb-4">{view360.incident.student_matric}</p>
                                                <div className="flex gap-4 text-sm">
                                                    <div><span className="opacity-70 block text-[10px] uppercase">Level</span>{view360.incident.student_level}</div>
                                                    <div><span className="opacity-70 block text-[10px] uppercase">Course</span>{view360.incident.course_code}</div>
                                                    <div><span className="opacity-70 block text-[10px] uppercase">Exam Date</span>{view360.incident.exam_date}</div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-[10px] uppercase tracking-wider text-blue-200 mb-1">Current Status</div>
                                                <div className="bg-white text-primary px-3 py-1 rounded font-bold text-sm uppercase">{view360.case.stage.replace('_', ' ')}</div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Narrative */}
                                    <div className="bg-gray-50 p-5 rounded-lg border border-gray-200">
                                        <h4 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 border-b pb-2">Incident Narrative</h4>
                                        <div className="text-sm font-bold text-gray-800 mb-2 capitalize">Offence: {view360.incident.offence_type.replace('_', ' ')}</div>
                                        <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{view360.incident.description}</p>
                                    </div>

                                    {/* Evidence & Hearing/Sanction Panels */}
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                            <h4 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 border-b pb-2"><i className="fas fa-paperclip mr-1"></i> Attached Evidence</h4>
                                            {view360.evidence && view360.evidence.length > 0 ? (
                                                <ul className="space-y-2">
                                                    {view360.evidence.map(e => (
                                                        <li key={e.id} className="flex items-center gap-2 text-sm bg-white p-2 rounded border border-gray-100 shadow-sm">
                                                            <i className={`fas ${e.mime_type.includes('pdf') ? 'fa-file-pdf text-red-500' : 'fa-image text-blue-500'}`}></i>
                                                            <span className="truncate flex-1" title={e.original_name}>{e.original_name}</span>
                                                            <a href={e.file_path} target="_blank" className="text-blue-500 hover:text-blue-700" title="Download"><i className="fas fa-download"></i></a>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : <p className="text-xs text-gray-500 italic">No digital evidence attached.</p>}
                                        </div>

                                        <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                            <h4 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 border-b pb-2"><i className="fas fa-gavel mr-1"></i> Adjudication Details</h4>
                                            {view360.sanction ? (
                                                <div className="text-sm space-y-2">
                                                    <div className="bg-red-100 text-red-800 px-2 py-1 rounded font-bold uppercase text-xs inline-block mb-1">{view360.sanction.sanction_type.replace('_', ' ')}</div>
                                                    <p className="text-gray-700"><strong>Duration:</strong> {view360.sanction.duration || 'N/A'}</p>
                                                    <p className="text-gray-700"><strong>Details:</strong> {view360.sanction.description}</p>
                                                    <p className="text-gray-500 text-xs mt-2">Effective: {view360.sanction.effective_date}</p>
                                                </div>
                                            ) : view360.hearings && view360.hearings.length > 0 ? (
                                                <div className="text-sm">
                                                    <div className="font-bold text-orange-600 mb-1">Hearing Scheduled</div>
                                                    <p className="text-gray-700"><i className="far fa-calendar-alt mr-1"></i> {view360.hearings[0].scheduled_date}</p>
                                                    <p className="text-gray-700"><i className="fas fa-map-marker-alt mr-1"></i> {view360.hearings[0].venue}</p>
                                                </div>
                                            ) : <p className="text-xs text-gray-500 italic">No hearings or sanctions recorded yet.</p>}
                                        </div>
                                    </div>
                                </div>

                                {/* Right Column: Case Logs / Audit Trail */}
                                <div className="bg-gray-50 rounded-xl border border-gray-200 flex flex-col h-[600px]">
                                    <div className="p-4 border-b border-gray-200 bg-gray-100 rounded-t-xl">
                                        <h4 className="text-sm font-bold text-gray-800"><i className="fas fa-history mr-2 text-primary"></i>Audit Timeline</h4>
                                    </div>
                                    <div className="flex-1 overflow-y-auto p-4 timeline-scroll">
                                        <div className="relative border-l-2 border-gray-200 ml-3 space-y-6 pb-4">
                                            {view360.logs && view360.logs.map((log, idx) => (
                                                <div key={log.id} className="relative pl-6">
                                                    <div className="absolute -left-1.5 top-1 w-3 h-3 bg-primary rounded-full border-2 border-white"></div>
                                                    <div className="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">{log.logged_at}</div>
                                                    <div className="text-sm font-bold text-gray-800 capitalize mb-1">{log.action.replace(/_/g, ' ')}</div>
                                                    <div className="text-xs text-blue-600 mb-1"><i className="fas fa-user mr-1"></i> {log.actor_name}</div>
                                                    {log.note && <div className="text-xs text-gray-600 bg-white p-2 border border-gray-100 rounded mt-1 shadow-sm italic">"{log.note}"</div>}
                                                </div>
                                            ))}
                                            {(!view360.logs || view360.logs.length === 0) && <p className="pl-6 text-xs text-gray-400">No logs available.</p>}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Modal>
                    )}
                </div>
            );
        };

        const AcademicStructureView = () => {
            const [activeTab, setActiveTab] = useState('faculties');
            
            return (
                <div className="space-y-6">
                    <div className="flex gap-4 border-b border-gray-200">
                        {['faculties', 'departments', 'courses'].map(tab => (
                            <button key={tab} onClick={() => setActiveTab(tab)} className={`py-3 px-4 font-bold text-sm border-b-2 transition ${activeTab === tab ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                                <i className={`fas ${tab==='faculties'?'fa-university':tab==='departments'?'fa-building':'fa-book'} mr-2`}></i>
                                {tab.charAt(0).toUpperCase() + tab.slice(1)}
                            </button>
                        ))}
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden p-6">
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="font-bold text-gray-800 capitalize">Manage {activeTab}</h3>
                            <button className="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold shadow-soft hover:shadow-card hover:bg-accent-light transition-all hover:-translate-y-0.5"><i className="fas fa-plus mr-2"></i> Add New</button>
                        </div>

                        {activeTab === 'faculties' && (
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 text-gray-500 uppercase font-bold"><tr><th className="p-3">Code</th><th className="p-3">Faculty Name</th><th className="p-3">Actions</th></tr></thead>
                                <tbody className="divide-y divide-gray-100">
                                    {lookups.faculties.map(f => <tr key={f.id}><td className="p-3 font-mono">{f.code}</td><td className="p-3 font-bold">{f.name}</td><td className="p-3"><button className="text-blue-500 hover:text-blue-700">Edit</button></td></tr>)}
                                </tbody>
                            </table>
                        )}

                        {activeTab === 'departments' && (
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 text-gray-500 uppercase font-bold"><tr><th className="p-3">Code</th><th className="p-3">Dept Name</th><th className="p-3">Faculty</th><th className="p-3">Actions</th></tr></thead>
                                <tbody className="divide-y divide-gray-100">
                                    {lookups.departments.map(d => <tr key={d.id}><td className="p-3 font-mono">{d.code}</td><td className="p-3 font-bold">{d.name}</td><td className="p-3 text-gray-500">{lookups.faculties.find(f=>f.id===d.faculty_id)?.name}</td><td className="p-3"><button className="text-blue-500 hover:text-blue-700">Edit</button></td></tr>)}
                                </tbody>
                            </table>
                        )}

                        {activeTab === 'courses' && (
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 text-gray-500 uppercase font-bold"><tr><th className="p-3">Code</th><th className="p-3">Course Title</th><th className="p-3">Dept</th><th className="p-3">Actions</th></tr></thead>
                                <tbody className="divide-y divide-gray-100">
                                    {lookups.courses.map(c => <tr key={c.id}><td className="p-3 font-mono font-bold text-primary">{c.code}</td><td className="p-3">{c.title}</td><td className="p-3 text-gray-500">{lookups.departments.find(d=>d.id===c.department_id)?.code}</td><td className="p-3"><button className="text-blue-500 hover:text-blue-700">Edit</button></td></tr>)}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            );
        };

        const AnalyticsView = () => {
            const chartRef1 = useRef(null);
            const chartRef2 = useRef(null);
            const [analytics, setAnalytics] = useState(null);

            useEffect(() => {
                apiCall('get_analytics').then(res => {
                    setAnalytics(res.data.data);
                    renderCharts(res.data.data);
                }).catch(() => {});
            }, []);

            const renderCharts = (data) => {
                const stages = data?.stages || { 'Reported': 10, 'Hearing': 5, 'Resolved': 20, 'Dismissed': 2 };
                const monthly = data?.monthly || { 'Jan': 2, 'Feb': 5, 'Mar': 12, 'Apr': 8 };

                new Chart(chartRef1.current, {
                    type: 'doughnut',
                    data: { labels: Object.keys(stages), datasets: [{ data: Object.values(stages), backgroundColor: ['#E5E7EB', '#FBBF24', '#34D399', '#9CA3AF'] }] },
                    options: { responsive: true, maintainAspectRatio: false }
                });

                new Chart(chartRef2.current, {
                    type: 'line',
                    data: { labels: Object.keys(monthly), datasets: [{ label: 'Incidents', data: Object.values(monthly), borderColor: '#C6A15B', tension: 0.4 }] },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            };

            return (
                <div className="space-y-6">
                    <h2 className="text-2xl font-bold text-gray-800">System Analytics</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                            <h3 className="font-bold text-gray-700 mb-4">Cases by Stage</h3>
                            <div className="h-64"><canvas ref={chartRef1}></canvas></div>
                        </div>
                        <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                            <h3 className="font-bold text-gray-700 mb-4">Incident Volume (YTD)</h3>
                            <div className="h-64"><canvas ref={chartRef2}></canvas></div>
                        </div>
                    </div>
                </div>
            );
        };

        const SettingsView = () => (
            <div className="max-w-2xl bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <h3 className="font-bold text-xl mb-6 border-b pb-2">Global Settings</h3>
                <form className="space-y-5">
                    <div>
                        <label className="block text-sm font-bold text-gray-700 mb-1">Current Academic Session</label>
                        <input className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none" defaultValue="2024/2025" />
                    </div>
                    <div>
                        <label className="block text-sm font-bold text-gray-700 mb-1">Active Semester</label>
                        <select className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none">
                            <option>First Semester</option>
                            <option>Second Semester</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-bold text-gray-700 mb-1">Require HOD Review Before Committee</label>
                        <div className="flex items-center gap-2 mt-2">
                            <input type="checkbox" className="w-4 h-4 text-primary" defaultChecked />
                            <span className="text-sm text-gray-600">Enable strict workflow</span>
                        </div>
                    </div>
                    <button className="bg-primary text-white px-6 py-2 rounded-lg text-sm font-bold shadow-soft hover:shadow-card hover:bg-accent-light transition-all hover:-translate-y-0.5">Save Configuration</button>
                </form>
            </div>
        );

        // --- Master Layout ---
        if (!user) {
            return (
                <div className="flex h-screen items-center justify-center bg-gray-50">
                    <div className="flex flex-col items-center gap-3 text-primary">
                        <i className="fas fa-circle-notch fa-spin text-3xl"></i>
                        <div className="text-sm font-bold text-gray-500 uppercase tracking-wide">Loading Admin Console…</div>
                    </div>
                </div>
            );
        }

        return (
            <div className="flex h-screen bg-gray-50 overflow-hidden">
                <aside className="w-64 bg-gradient-to-b from-primary to-primary-dark text-white flex flex-col h-full shadow-card z-20 relative">
                    <div className="h-16 flex items-center gap-3 px-6 font-display font-extrabold text-xl tracking-tight border-b border-white/10 bg-black/10">
                        <img src="assets/buk-logo.png" alt="BUK crest" className="w-9 h-9 rounded-lg bg-white p-0.5 shadow-soft shrink-0 object-contain" />
                        <span className="leading-tight">BUK<span className="text-accent-light ml-1">EIMS</span><small className="block text-[9px] font-semibold tracking-[0.12em] text-white/60">BAYERO UNIVERSITY KANO</small></span>
                    </div>
                    <div className="p-4 flex items-center gap-3 border-b border-white/10">
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-accent to-accent-light ring-2 ring-white/15 flex items-center justify-center font-bold text-lg shadow-soft shrink-0"><i className="fas fa-user-shield"></i></div>
                        <div>
                            <div className="text-sm font-bold truncate w-40">{user?.full_name}</div>
                            <div className="text-[10px] uppercase tracking-wider text-blue-200">Administrator</div>
                        </div>
                    </div>
                    <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
                        <div className="text-xs font-bold text-blue-300/50 uppercase tracking-wider mb-2 mt-2 px-2">Core</div>
                        <button onClick={()=>setView('dashboard')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='dashboard'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-chart-pie w-5"></i> Dashboard</button>
                        <button onClick={()=>setView('users')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='users'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-users-cog w-5"></i> Manage Users</button>
                        
                        <div className="text-xs font-bold text-blue-300/50 uppercase tracking-wider mb-2 mt-6 px-2">Workflow Management</div>
                        <button onClick={()=>setView('incidents')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='incidents'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-folder-open w-5"></i> All Incidents</button>
                        <button onClick={()=>setView('cases')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='cases'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-gavel w-5"></i> All Cases</button>

                        <div className="text-xs font-bold text-blue-300/50 uppercase tracking-wider mb-2 mt-6 px-2">Configuration</div>
                        <button onClick={()=>setView('structure')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='structure'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-sitemap w-5"></i> Academic Structure</button>
                        <button onClick={()=>setView('analytics')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='analytics'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-chart-area w-5"></i> Reports & Analytics</button>
                        <button onClick={()=>setView('settings')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='settings'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-cogs w-5"></i> System Settings</button>
                    </nav>
                </aside>

                <div className="flex-1 flex flex-col min-w-0">
                    <header className="h-16 bg-white/95 backdrop-blur-sm shadow-soft flex items-center justify-between px-8 z-10 border-b border-gray-100">
                        <h1 className="text-xl font-display font-extrabold text-gray-800 capitalize tracking-tight">{view.replace('_', ' ')}</h1>
                        <div className="flex items-center gap-6">
                            <button className="text-gray-400 hover:text-primary relative" title="Notifications">
                                <i className="fas fa-bell text-xl"></i>
                                <span className="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold border-2 border-white">2</span>
                            </button>
                            <button onClick={logout} className="text-red-500 hover:bg-red-50 px-3 py-1.5 rounded transition text-sm font-bold flex items-center gap-2">
                                <i className="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </div>
                    </header>
                    <main className="flex-1 overflow-y-auto p-8">
                        {view === 'dashboard' ? <DashboardView/> : 
                         view === 'users' ? <UsersView/> : 
                         view === 'incidents' ? <IncidentsView/> :
                         view === 'cases' ? <CasesView/> :
                         view === 'structure' ? <AcademicStructureView/> : 
                         view === 'analytics' ? <AnalyticsView/> : 
                         <SettingsView/>}
                    </main>
                </div>
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<AdminApp />);
</script>
</body>
</html>
