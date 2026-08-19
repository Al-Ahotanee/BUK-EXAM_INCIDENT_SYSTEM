<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal - BUK EIMS</title>
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
        h1,h2,h3,h4,h5,h6,.font-display { font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; letter-spacing: -0.01em; }
        ::selection { background: #C6A15B; color: #fff; }
        body { -webkit-font-smoothing: antialiased; }
    </style>
</head>
<body class="bg-gray-50 text-gray-700 font-sans">
<div id="root"></div>

<script type="text/babel">
    const API = './api.php';
    const { useState, useEffect } = React;

    // --- Reusable UI Components ---
    const Modal = ({ title, onClose, children, onSubmit, submitText = "Save", loading = false, width = "max-w-lg" }) => (
        <div className="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className={`bg-white rounded-xl shadow-2xl w-full ${width} overflow-hidden flex flex-col max-h-[90vh]`}>
                <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 className="font-bold text-lg text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-red-500"><i className="fas fa-times"></i></button>
                </div>
                <div className="p-6 overflow-y-auto">
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
            <span className={`px-2.5 py-1 rounded-md text-xs font-bold border capitalize ${getStyle(status)}`}>
                {status?.replace('_', ' ')}
            </span>
        );
    };

    function UserApp() {
        const [user, setUser] = useState(null);
        const [csrf, setCsrf] = useState('');
        const [view, setView] = useState('dashboard');
        const [lookups, setLookups] = useState({ courses: [], departments: [] });
        
        useEffect(() => {
            axios.post(`${API}?action=check_auth`).then(res => {
                if (res.data.isAuthenticated && ['invigilator', 'hod', 'committee'].includes(res.data.user.role)) {
                    setUser(res.data.user);
                    setCsrf(res.data.csrf_token);
                    if (res.data.user.role === 'invigilator') fetchLookups();
                } else {
                    window.location.href = 'index.php';
                }
            });
        }, []);

        const fetchLookups = () => apiCall('get_lookups').then(r => setLookups(r.data.data));
        const apiCall = async (action, data = {}) => axios.post(`${API}?action=${action}`, data, { headers: { 'X-Csrf-Token': csrf } });
        const logout = () => apiCall('logout').then(() => window.location.href = 'index.php');

        // --- DASHBOARD VIEW ---
        const DashboardView = () => {
            const [stats, setStats] = useState({ reports: 0, pending: 0, dept_incidents: 0, active_dept_cases: 0, assigned: 0, pending_hearings: 0 });
            const [loading, setLoading] = useState(true);

            useEffect(() => {
                setLoading(true);
                apiCall('get_user_dashboard').then(res => setStats(res.data.data)).catch(console.error).finally(() => setLoading(false));
            }, []);

            const StatCard = ({ value, label, color }) => (
                <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-soft hover:shadow-card transition-shadow">
                    <div className="text-gray-500 text-xs font-bold uppercase mb-2 tracking-wide">{label}</div>
                    {loading ? (
                        <div className="h-8 w-14 bg-gray-100 rounded animate-pulse"></div>
                    ) : (
                        <div className={`text-3xl font-extrabold ${color}`}>{value || 0}</div>
                    )}
                </div>
            );

            return (
                <div className="space-y-6">
                    <h2 className="text-2xl font-display font-bold text-gray-900 tracking-tight">Welcome, {user.full_name}</h2>
                    <p className="text-gray-600">This is your personalized {user.role.replace('_', ' ')} portal.</p>
                    
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                        {user.role === 'invigilator' && (
                            <>
                                <StatCard value={stats.reports} label="My Reports" color="text-primary" />
                                <StatCard value={stats.pending} label="Pending Review" color="text-yellow-600" />
                            </>
                        )}
                        {user.role === 'hod' && (
                            <>
                                <StatCard value={stats.dept_incidents} label="Dept Incidents" color="text-primary" />
                                <StatCard value={stats.active_dept_cases} label="Active Dept Cases" color="text-blue-600" />
                            </>
                        )}
                        {user.role === 'committee' && (
                            <>
                                <StatCard value={stats.assigned} label="Assigned Cases" color="text-primary" />
                                <StatCard value={stats.pending_hearings} label="Pending Hearings" color="text-orange-600" />
                            </>
                        )}
                    </div>
                </div>
            );
        };

        // --- NEW REPORT VIEW (Invigilator Only) ---
        const ReportIncidentView = () => {
            const [form, setForm] = useState({
                student_name: '', student_matric: '', student_dept_id: '', student_level: '100',
                course_id: '', exam_date: '', exam_time: '', venue: '', semester: 'First',
                academic_session: '2024/2025', offence_type: 'foreign_material', description: ''
            });
            const [msg, setMsg] = useState({text:'', type:''});
            const [loading, setLoading] = useState(false);

            const submitReport = async (e) => {
                e.preventDefault(); setLoading(true); setMsg({text:'', type:''});
                
                const fd = new FormData();
                Object.keys(form).forEach(k => fd.append(k, form[k]));
                
                const fileInput = document.querySelector('input[name="evidence"]');
                if (fileInput && fileInput.files.length > 0) {
                    fd.append('evidence', fileInput.files[0]);
                }

                try {
                    await axios.post(`${API}?action=report_incident`, fd, { 
                        headers: { 'X-Csrf-Token': csrf } 
                    });
                    setMsg({text: 'Incident reported successfully. A reference number has been generated.', type: 'success'});
                    setForm({...form, description: '', student_name: '', student_matric: ''});
                    if (fileInput) fileInput.value = '';
                } catch(err) {
                    setMsg({text: err.response?.data?.message || 'Failed to submit report', type: 'error'});
                } finally { setLoading(false); }
            };

            return (
                <div className="max-w-4xl bg-white p-8 rounded-xl border border-gray-200 shadow-sm">
                    <h3 className="font-bold text-2xl text-gray-900 mb-6 border-b pb-4">Log Examination Malpractice</h3>
                    {msg.text && <div className={`p-4 rounded-md mb-6 font-bold ${msg.type==='success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>{msg.text}</div>}
                    
                    <form onSubmit={submitReport} className="space-y-8">
                        {/* Section 1: Exam Details */}
                        <div>
                            <h4 className="text-sm font-bold text-primary uppercase tracking-wider mb-4"><i className="fas fa-file-alt mr-2"></i> 1. Examination Details</h4>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Course *</label>
                                    <select required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.course_id} onChange={e=>setForm({...form, course_id:e.target.value})}>
                                        <option value="">-- Select Course --</option>
                                        {lookups.courses.map(c => <option key={c.id} value={c.id}>{c.code} - {c.title}</option>)}
                                    </select>
                                </div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Exam Date *</label><input type="date" required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.exam_date} onChange={e=>setForm({...form, exam_date:e.target.value})}/></div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Exam Time *</label><input type="time" required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.exam_time} onChange={e=>setForm({...form, exam_time:e.target.value})}/></div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Venue *</label><input required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" placeholder="e.g. CBT Centre" value={form.venue} onChange={e=>setForm({...form, venue:e.target.value})}/></div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Semester *</label>
                                    <select required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.semester} onChange={e=>setForm({...form, semester:e.target.value})}><option>First</option><option>Second</option></select>
                                </div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Academic Session *</label><input required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" placeholder="2024/2025" value={form.academic_session} onChange={e=>setForm({...form, academic_session:e.target.value})}/></div>
                            </div>
                        </div>

                        {/* Section 2: Student Info */}
                        <div>
                            <h4 className="text-sm font-bold text-primary uppercase tracking-wider mb-4"><i className="fas fa-user-graduate mr-2"></i> 2. Student Information</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Student Full Name *</label><input required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.student_name} onChange={e=>setForm({...form, student_name:e.target.value})}/></div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Matriculation Number *</label><input required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" placeholder="BUK/..." value={form.student_matric} onChange={e=>setForm({...form, student_matric:e.target.value})}/></div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Student Department *</label>
                                    <select required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.student_dept_id} onChange={e=>setForm({...form, student_dept_id:e.target.value})}>
                                        <option value="">-- Select Dept --</option>
                                        {lookups.departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                    </select>
                                </div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Level *</label>
                                    <select required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.student_level} onChange={e=>setForm({...form, student_level:e.target.value})}><option>100</option><option>200</option><option>300</option><option>400</option><option>500</option></select>
                                </div>
                            </div>
                        </div>

                        {/* Section 3: Offence Details */}
                        <div>
                            <h4 className="text-sm font-bold text-primary uppercase tracking-wider mb-4"><i className="fas fa-exclamation-circle mr-2"></i> 3. Offence Details & Evidence</h4>
                            <div className="space-y-4">
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Offence Type *</label>
                                    <select required className="w-full border p-2.5 rounded-md text-sm outline-none focus:border-accent" value={form.offence_type} onChange={e=>setForm({...form, offence_type:e.target.value})}>
                                        <option value="foreign_material">Foreign Material (Notes, Textbooks)</option>
                                        <option value="electronic_device">Unauthorized Electronic Device</option>
                                        <option value="impersonation">Impersonation</option>
                                        <option value="collusion">Collusion / Copying</option>
                                        <option value="assault">Assault / Insubordination</option>
                                        <option value="misconduct">General Misconduct</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Detailed Description * (Min 50 chars)</label>
                                    <textarea required minLength="50" className="w-full border p-3 rounded-md text-sm outline-none focus:border-accent h-32" placeholder="Provide a detailed narrative of how the incident was discovered..." value={form.description} onChange={e=>setForm({...form, description:e.target.value})}></textarea>
                                </div>
                                <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition relative">
                                    <i className="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                    <p className="text-sm text-gray-600 font-bold">Attach Evidence (Optional)</p>
                                    <p className="text-xs text-gray-500 mb-4">JPEG, PNG, or PDF up to 5MB</p>
                                    <input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf" className="text-sm border p-2 rounded block mx-auto cursor-pointer" />
                                </div>
                            </div>
                        </div>

                        <div className="pt-4 border-t border-gray-100 flex justify-end">
                            <button type="submit" disabled={loading} className="bg-primary hover:bg-accent-light text-white px-8 py-3 rounded-lg font-bold shadow-card hover:shadow-xl transition-all hover:-translate-y-0.5 flex items-center gap-2">
                                {loading ? <i className="fas fa-spinner fa-spin"></i> : <><i className="fas fa-paper-plane"></i> Submit Formal Report</>}
                            </button>
                        </div>
                    </form>
                </div>
            );
        };

        // --- LIST VIEWS (Incidents & Cases) ---
        const ListView = ({ type }) => {
            const [records, setRecords] = useState([]);
            const [loading, setLoading] = useState(false);
            
            // Modal States
            const [activeModal, setActiveModal] = useState(null);
            const [selectedId, setSelectedId] = useState(null);
            const [form, setForm] = useState({});
            const [msg, setMsg] = useState({text:'', type:''});

            const fetchData = () => {
                setLoading(true);
                apiCall(type === 'incidents' ? 'get_incidents' : 'get_cases')
                    .then(res => setRecords(res.data.data))
                    .finally(() => setLoading(false));
            };
            useEffect(() => { fetchData(); }, [type]);

            // Handlers for specific roles
            const handleAddNote = async (e) => {
                e.preventDefault();
                try {
                    await apiCall('add_case_note', { case_id: selectedId, note: form.note });
                    setActiveModal(null); fetchData();
                    alert("Official note appended to case log.");
                } catch(err) { setMsg({text: err.response?.data?.message || 'Error', type:'error'}); }
            };

            const handleScheduleHearing = async (e) => {
                e.preventDefault();
                try {
                    await apiCall('schedule_hearing', { case_id: selectedId, date: form.date, venue: form.venue });
                    setActiveModal(null); fetchData();
                    alert("Hearing Scheduled successfully.");
                } catch(err) { setMsg({text: err.response?.data?.message || 'Error', type:'error'}); }
            };

            const handleResolveCase = async (e) => {
                e.preventDefault();
                try {
                    await apiCall('resolve_case', { 
                        case_id: selectedId, 
                        resolution_summary: form.summary,
                        status: form.status, // resolved or dismissed
                        sanction_type: form.sanction_type,
                        duration: form.duration,
                        description: form.sanction_desc,
                        effective_date: form.effective_date
                    });
                    setActiveModal(null); fetchData();
                    alert(`Case successfully marked as ${form.status}.`);
                } catch(err) { setMsg({text: err.response?.data?.message || 'Error', type:'error'}); }
            };

            return (
                <div className="space-y-6">
                    <div className="flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <h3 className="font-bold text-lg text-gray-800 capitalize">
                            {user.role === 'invigilator' ? 'My ' : user.role === 'hod' ? 'Department ' : 'Assigned '} 
                            {type}
                        </h3>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50/80 text-[11px] uppercase tracking-wider text-gray-500 border-b border-gray-200 sticky top-0">
                                <tr>
                                    <th className="p-4 font-bold">Reference / ID</th>
                                    <th className="p-4 font-bold">Student</th>
                                    <th className="p-4 font-bold">Status/Stage</th>
                                    {type === 'cases' && <th className="p-4 font-bold text-right">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? <tr><td colSpan="4" className="p-8 text-center text-gray-400">Loading...</td></tr> : records.length === 0 ? <tr><td colSpan="4" className="p-8 text-center text-gray-400">No records found.</td></tr> : records.map(r => (
                                    <tr key={r.id} className="hover:bg-gray-50 transition">
                                        <td className="p-4">
                                            <div className="font-bold text-primary">{type === 'cases' ? r.case_no : r.reference_no}</div>
                                            <div className="text-xs text-gray-500 mt-0.5">{type === 'cases' ? r.opened_at?.split(' ')[0] : r.exam_date}</div>
                                        </td>
                                        <td className="p-4">
                                            <div className="font-bold text-gray-900">{r.student_name}</div>
                                            <div className="text-xs text-gray-500 font-mono mt-0.5">{r.student_matric}</div>
                                        </td>
                                        <td className="p-4">
                                            <StatusBadge status={type === 'cases' ? r.stage : r.status} />
                                        </td>
                                        {type === 'cases' && (
                                            <td className="p-4 text-right">
                                                {/* HOD Actions */}
                                                {user.role === 'hod' && (
                                                    <button onClick={() => { setSelectedId(r.id); setActiveModal('note'); setForm({}); setMsg({text:'', type:''}); }} className="text-xs font-bold bg-blue-50 text-blue-600 px-3 py-1.5 rounded border border-blue-200 hover:bg-blue-100">
                                                        <i className="fas fa-comment-medical mr-1"></i> Add Note
                                                    </button>
                                                )}

                                                {/* Committee Actions - Fully Expanded */}
                                                {user.role === 'committee' && (
                                                    <div className="flex justify-end items-center gap-2">
                                                        {/* Committee can always add notes unless closed */}
                                                        {!['resolved', 'dismissed'].includes(r.stage) && (
                                                            <button onClick={() => { setSelectedId(r.id); setActiveModal('note'); setForm({}); setMsg({text:'', type:''}); }} className="text-xs font-bold bg-blue-50 text-blue-600 px-3 py-1.5 rounded border border-blue-200 hover:bg-blue-100" title="Add Investigation Note">
                                                                <i className="fas fa-comment-medical mr-1"></i> Note
                                                            </button>
                                                        )}
                                                        
                                                        {/* Schedule hearing from under_review or investigation */}
                                                        {['under_review', 'investigation'].includes(r.stage) && (
                                                            <button onClick={() => { setSelectedId(r.id); setActiveModal('schedule'); setForm({}); setMsg({text:'', type:''}); }} className="text-xs font-bold bg-yellow-500 text-white px-3 py-1.5 rounded shadow hover:bg-yellow-600">
                                                                Schedule Hearing
                                                            </button>
                                                        )}
                                                        
                                                        {/* Resolve case only from hearing */}
                                                        {r.stage === 'hearing' && (
                                                            <button onClick={() => { setSelectedId(r.id); setActiveModal('resolve'); setForm({status: 'resolved'}); setMsg({text:'', type:''}); }} className="text-xs font-bold bg-green-600 text-white px-3 py-1.5 rounded shadow hover:bg-green-700">
                                                                Adjudicate
                                                            </button>
                                                        )}
                                                    </div>
                                                )}
                                                
                                                {/* Closed Status */}
                                                {['resolved', 'dismissed'].includes(r.stage) && (
                                                    <span className="text-xs text-gray-400 font-bold uppercase"><i className="fas fa-lock text-[10px]"></i> Closed</span>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* MODALS */}
                    {/* Note Modal */}
                    {activeModal === 'note' && (
                        <Modal title="Add Official Case Note" onClose={() => setActiveModal(null)} submitText="Append Note" onSubmit={handleAddNote}>
                            {msg.text && <div className="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm border border-red-200">{msg.text}</div>}
                            <div className="space-y-4">
                                <p className="text-sm text-gray-600">This note will be permanently appended to the official case audit log and visible to the Investigating Officer and Committee.</p>
                                <textarea required className="w-full border border-gray-300 p-3 rounded-md text-sm focus:border-accent outline-none h-32 resize-none" placeholder="Enter your observations or departmental recommendations here..." value={form.note || ''} onChange={e=>setForm({...form, note:e.target.value})}></textarea>
                            </div>
                        </Modal>
                    )}

                    {/* Committee Schedule Hearing Modal */}
                    {activeModal === 'schedule' && (
                        <Modal title="Schedule Disciplinary Hearing" onClose={() => setActiveModal(null)} submitText="Schedule" onSubmit={handleScheduleHearing}>
                            {msg.text && <div className="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm border border-red-200">{msg.text}</div>}
                            <div className="space-y-4">
                                <p className="text-sm text-gray-600 mb-4">Scheduling a hearing will officially advance this case to the Hearing stage and notify all parties.</p>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Date & Time *</label><input type="datetime-local" required className="w-full border border-gray-300 p-2.5 rounded-md text-sm outline-none" value={form.date || ''} onChange={e=>setForm({...form, date:e.target.value})}/></div>
                                <div><label className="block text-xs font-bold text-gray-700 mb-1">Venue *</label><input type="text" placeholder="e.g. Committee Room B" required className="w-full border border-gray-300 p-2.5 rounded-md text-sm outline-none" value={form.venue || ''} onChange={e=>setForm({...form, venue:e.target.value})}/></div>
                            </div>
                        </Modal>
                    )}

                    {/* Committee Resolve/Sanction Modal */}
                    {activeModal === 'resolve' && (
                        <Modal title="Record Final Adjudication" width="max-w-2xl" onClose={() => setActiveModal(null)} submitText="Submit Final Verdict" onSubmit={handleResolveCase}>
                            {msg.text && <div className="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm border border-red-200">{msg.text}</div>}
                            <form className="space-y-6">
                                <div className="bg-red-50 border-l-4 border-red-500 p-3 text-sm text-red-800 font-bold">
                                    Warning: Adjudicating a case is a final action. The stage will be permanently closed.
                                </div>
                                
                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Final Verdict *</label>
                                    <select className="w-full border border-gray-300 p-2.5 rounded-md text-sm focus:border-accent outline-none bg-white font-bold" value={form.status || 'resolved'} onChange={e=>setForm({...form, status:e.target.value})}>
                                        <option value="resolved">Guilty - Apply Sanctions</option>
                                        <option value="dismissed">Not Guilty - Dismiss Case</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-gray-700 mb-1">Resolution Summary / Committee Notes *</label>
                                    <textarea required className="w-full border border-gray-300 p-3 rounded-md text-sm focus:border-accent outline-none h-24" placeholder="Summarize the committee's findings..." value={form.summary || ''} onChange={e=>setForm({...form, summary:e.target.value})}></textarea>
                                </div>

                                {form.status !== 'dismissed' && (
                                    <div className="border-t border-gray-200 pt-4 mt-4">
                                        <h4 className="font-bold text-primary mb-4"><i className="fas fa-gavel mr-2"></i> Official Sanction Details</h4>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-xs font-bold text-gray-700 mb-1">Sanction Type *</label>
                                                <select required className="w-full border border-gray-300 p-2.5 rounded-md text-sm outline-none bg-white" value={form.sanction_type || ''} onChange={e=>setForm({...form, sanction_type:e.target.value})}>
                                                    <option value="">-- Select Sanction --</option>
                                                    <option value="warning">Formal Warning</option>
                                                    <option value="course_cancellation">Cancellation of Course Results</option>
                                                    <option value="suspension_semester">Suspension (1 Semester)</option>
                                                    <option value="suspension_year">Suspension (1 Academic Year)</option>
                                                    <option value="expulsion">Expulsion</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div><label className="block text-xs font-bold text-gray-700 mb-1">Effective Date *</label><input type="date" required className="w-full border border-gray-300 p-2.5 rounded-md text-sm outline-none" value={form.effective_date || ''} onChange={e=>setForm({...form, effective_date:e.target.value})}/></div>
                                            <div className="md:col-span-2"><label className="block text-xs font-bold text-gray-700 mb-1">Duration Specifics (Optional)</label><input type="text" placeholder="e.g. Valid for 2024/2025 Session" className="w-full border border-gray-300 p-2.5 rounded-md text-sm outline-none" value={form.duration || ''} onChange={e=>setForm({...form, duration:e.target.value})}/></div>
                                            <div className="md:col-span-2"><label className="block text-xs font-bold text-gray-700 mb-1">Sanction Description *</label><textarea required className="w-full border border-gray-300 p-3 rounded-md text-sm focus:border-accent outline-none h-20" placeholder="Formal description of the penalty applied..." value={form.sanction_desc || ''} onChange={e=>setForm({...form, sanction_desc:e.target.value})}></textarea></div>
                                        </div>
                                    </div>
                                )}
                            </form>
                        </Modal>
                    )}
                </div>
            );
        };

        // --- Master Layout ---
        if (!user) {
            return (
                <div className="flex h-screen items-center justify-center bg-gray-50">
                    <div className="flex flex-col items-center gap-3 text-primary">
                        <i className="fas fa-circle-notch fa-spin text-3xl"></i>
                        <div className="text-sm font-bold text-gray-500 uppercase tracking-wide">Loading Portal…</div>
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
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-accent to-accent-light ring-2 ring-white/15 flex items-center justify-center font-bold text-lg shadow-soft shrink-0"><i className="fas fa-user"></i></div>
                        <div>
                            <div className="text-sm font-bold truncate w-40">{user?.full_name}</div>
                            <div className="text-[10px] uppercase tracking-wider text-blue-200">{user?.role.replace('_', ' ')} Portal</div>
                        </div>
                    </div>
                    <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
                        <div className="text-xs font-bold text-blue-300/50 uppercase tracking-wider mb-2 mt-2 px-2">Navigation</div>
                        <button onClick={()=>setView('dashboard')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='dashboard'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-home w-5"></i> Dashboard</button>
                        
                        {user.role === 'invigilator' && (
                            <>
                                <button onClick={()=>setView('new_report')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='new_report'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-plus-circle w-5"></i> Report Incident</button>
                                <button onClick={()=>setView('incidents')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='incidents'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-list-alt w-5"></i> My Reports</button>
                            </>
                        )}
                        
                        {user.role === 'hod' && (
                            <>
                                <button onClick={()=>setView('incidents')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='incidents'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-file-alt w-5"></i> Dept Incidents</button>
                                <button onClick={()=>setView('cases')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='cases'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-folder-open w-5"></i> Dept Cases</button>
                            </>
                        )}

                        {user.role === 'committee' && (
                            <>
                                <button onClick={()=>setView('cases')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='cases'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-gavel w-5"></i> Assigned Cases</button>
                            </>
                        )}
                    </nav>
                </aside>

                <div className="flex-1 flex flex-col min-w-0">
                    <header className="h-16 bg-white/95 backdrop-blur-sm shadow-soft flex items-center justify-between px-8 z-10 border-b border-gray-100">
                        <h1 className="text-xl font-display font-extrabold text-gray-800 capitalize tracking-tight">
                            {view.replace('_', ' ')}
                        </h1>
                        <div className="flex items-center gap-6">
                            <button onClick={logout} className="text-red-500 hover:bg-red-50 px-3 py-1.5 rounded transition text-sm font-bold flex items-center gap-2">
                                <i className="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </div>
                    </header>
                    <main className="flex-1 overflow-y-auto p-8">
                        {view === 'dashboard' ? <DashboardView/> : 
                         view === 'new_report' ? <ReportIncidentView/> : 
                         view === 'incidents' ? <ListView type="incidents"/> : 
                         <ListView type="cases"/>}
                    </main>
                </div>
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<UserApp />);
</script>
</body>
</html>
