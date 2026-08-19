<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Officer - BUK EIMS</title>
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
        /* Print Styles for the Notice Board Report */
        @media print {
            body { background-color: white !important; color: black !important; }
            .no-print, aside, header, button { display: none !important; }
            main { padding: 0 !important; margin: 0 !important; overflow: visible !important; width: 100% !important; }
            .print-only { display: block !important; }
            .shadow-sm, .shadow-lg, .shadow-2xl { box-shadow: none !important; border: none !important; }
            .print-container { width: 100% !important; max-width: 100% !important; }
            table { border-collapse: collapse !important; width: 100% !important; }
            th, td { border: 1px solid #000 !important; padding: 8px !important; text-align: left !important; }
            th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
        }
        .print-only { display: none; }
        
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
    const { useState, useEffect } = React;

    // --- Reusable UI Components ---
    const Modal = ({ title, onClose, children, onSubmit, submitText = "Save", loading = false, width = "max-w-lg" }) => (
        <div className="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 no-print">
            <div className={`bg-white rounded-xl shadow-2xl w-full ${width} overflow-hidden flex flex-col max-h-[95vh]`}>
                <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 className="font-bold text-lg text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-red-500 transition"><i className="fas fa-times text-xl"></i></button>
                </div>
                <div className="p-6 overflow-y-auto flex-1">
                    {children}
                </div>
                <div className="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                    <button onClick={onClose} className="px-4 py-2 text-sm font-bold text-gray-600 hover:text-gray-900">Close</button>
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

    function EOApp() {
        const [user, setUser] = useState(null);
        const [csrf, setCsrf] = useState('');
        const [view, setView] = useState('dashboard');
        const [lookups, setLookups] = useState({ committee_members: [], departments: [], faculties: [], courses: [] });
        
        useEffect(() => {
            axios.post(`${API}?action=check_auth`).then(res => {
                if (res.data.isAuthenticated && res.data.user.role === 'exam_officer') {
                    setUser(res.data.user);
                    setCsrf(res.data.csrf_token);
                    fetchLookups();
                } else window.location.href = 'index.php';
            });
        }, []);

        const fetchLookups = () => apiCall('get_lookups').then(r => setLookups(r.data.data));
        const apiCall = async (action, data = {}) => axios.post(`${API}?action=${action}`, data, { headers: { 'X-Csrf-Token': csrf } });
        const logout = () => apiCall('logout').then(() => window.location.href = 'index.php');

        // --- VIEWS ---

        const DashboardView = () => {
            const [stats, setStats] = useState({ total_incidents: 0, open_cases: 0, under_review: 0, resolved_this_month: 0, pending_incidents: 0 });
            const [loading, setLoading] = useState(true);
            useEffect(() => { setLoading(true); apiCall('get_eo_dashboard').then(res => setStats(res.data.data)).catch(() => {}).finally(() => setLoading(false)); }, []);

            const StatCard = ({ icon, iconBg, iconColor, value, label }) => (
                <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-soft hover:shadow-card transition-shadow flex items-center gap-4">
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
                    <h2 className="text-2xl font-display font-bold text-gray-900 tracking-tight">Registry Overview</h2>
                    {!loading && stats.pending_incidents > 0 && (
                        <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg shadow-sm flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <i className="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                                <div>
                                    <h4 className="text-yellow-800 font-bold">Action Required</h4>
                                    <p className="text-yellow-700 text-sm">There are {stats.pending_incidents} new incident reports awaiting triage.</p>
                                </div>
                            </div>
                            <button onClick={() => setView('incidents')} className="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 px-4 py-2 rounded-md font-bold text-sm transition">Review Incidents</button>
                        </div>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <StatCard icon="fa-folder-open" iconBg="bg-blue-50" iconColor="text-primary" value={stats.total_incidents} label="Total Incidents" />
                        <StatCard icon="fa-gavel" iconBg="bg-blue-50" iconColor="text-blue-600" value={stats.open_cases} label="Active Cases" />
                        <StatCard icon="fa-hourglass-half" iconBg="bg-yellow-50" iconColor="text-yellow-600" value={stats.under_review} label="Under Review" />
                        <StatCard icon="fa-check-circle" iconBg="bg-green-50" iconColor="text-green-600" value={stats.resolved_this_month} label="Resolved (Month)" />
                    </div>
                </div>
            );
        };

        const IncidentsView = () => {
            const [incidents, setIncidents] = useState([]);
            const [loading, setLoading] = useState(false);
            
            const fetchIncidents = () => { setLoading(true); apiCall('get_incidents').then(res => setIncidents(res.data.data)).finally(() => setLoading(false)); };
            useEffect(() => { fetchIncidents(); }, []);

            const handleOpenCase = async (incidentId) => {
                if(!confirm("Are you sure you want to officially open a case for this incident?")) return;
                try {
                    await apiCall('open_case', { incident_id: incidentId });
                    fetchIncidents(); alert("Case opened successfully! View it in the Case Management tab.");
                } catch(err) { alert(err.response?.data?.message || 'Error opening case'); }
            };

            return (
                <div className="space-y-6">
                    <div className="flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <h3 className="font-bold text-lg text-gray-800">Incident Reports Registry</h3>
                    </div>
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <table className="w-full text-left">
                            <thead className="bg-slate-50/80 text-[11px] uppercase tracking-wider text-gray-500 border-b border-gray-200 sticky top-0">
                                <tr><th className="p-4">Ref & Date</th><th className="p-4">Student Info</th><th className="p-4">Offence Details</th><th className="p-4 text-center">Status</th><th className="p-4 text-right">Actions</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loading ? <tr><td colSpan="5" className="p-8 text-center text-gray-400">Loading...</td></tr> : incidents.length === 0 ? <tr><td colSpan="5" className="p-8 text-center text-gray-400">No incidents found.</td></tr> : incidents.map(inc => (
                                    <tr key={inc.id} className="hover:bg-gray-50">
                                        <td className="p-4"><div className="font-bold text-primary">{inc.reference_no}</div><div className="text-xs text-gray-500">{inc.exam_date}</div></td>
                                        <td className="p-4"><div className="font-bold text-gray-900">{inc.student_name}</div><div className="text-xs text-gray-500 font-mono">{inc.student_matric}</div></td>
                                        <td className="p-4"><div className="text-sm font-bold text-gray-700 capitalize">{inc.offence_type.replace('_', ' ')}</div><div className="text-xs text-gray-500">{inc.course_code}</div></td>
                                        <td className="p-4 text-center"><StatusBadge status={inc.status} /></td>
                                        <td className="p-4 text-right">
                                            {inc.status === 'pending' ? (
                                                <button onClick={() => handleOpenCase(inc.id)} className="bg-accent hover:bg-blue-600 text-white px-3 py-1.5 rounded-md text-xs font-bold shadow transition"><i className="fas fa-folder-plus mr-1"></i> Open Case</button>
                                            ) : <span className="text-xs text-gray-400"><i className="fas fa-check-circle"></i> Processed</span>}
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
            
            // Workflow Modals
            const [activeModal, setActiveModal] = useState(null); 
            const [selectedCase, setSelectedCase] = useState(null);
            const [form, setForm] = useState({});
            
            // 360 View State
            const [view360, setView360] = useState(null); // Holds detailed case data
            const [loading360, setLoading360] = useState(false);

            const fetchCases = () => { setLoading(true); apiCall('get_cases').then(res => setCases(res.data.data)).finally(() => setLoading(false)); };
            useEffect(() => { fetchCases(); }, []);

            const open360View = async (caseId) => {
                setLoading360(true);
                try {
                    const res = await apiCall('get_case_details', { case_id: caseId });
                    setView360(res.data.data);
                } catch(e) {
                    alert("Failed to load case details.");
                } finally { setLoading360(false); }
            };

            const submitModal = async (e) => {
                e.preventDefault();
                try {
                    if (activeModal === 'assign') await apiCall('assign_committee', { case_id: selectedCase.id, committee_id: form.committee_id });
                    else if (activeModal === 'investigate') await apiCall('advance_case_stage', { case_id: selectedCase.id, stage: 'investigation', note: form.note });
                    fetchCases(); setActiveModal(null);
                } catch(err) { alert('Action failed.'); }
            };

            return (
                <div className="space-y-6">
                    <div className="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex justify-between items-center">
                        <h3 className="font-bold text-lg text-gray-800">Case Workflow Management</h3>
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
                                                <div className="text-xs font-bold text-gray-800">{lookups.committee_members.find(m => m.id == c.assigned_committee)?.full_name || 'Assigned'}<button onClick={() => {setSelectedCase(c); setForm({committee_id: c.assigned_committee}); setActiveModal('assign');}} className="ml-2 text-blue-500 hover:underline">Change</button></div>
                                            ) : (
                                                <button onClick={() => {setSelectedCase(c); setForm({}); setActiveModal('assign');}} className="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded hover:bg-blue-100 border border-blue-200"><i className="fas fa-user-plus mr-1"></i> Assign</button>
                                            )}
                                        </td>
                                        <td className="p-4 text-right flex justify-end gap-2">
                                            {/* 360 View Button */}
                                            <button onClick={() => open360View(c.id)} className="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-md text-xs font-bold transition border border-gray-300" title="Full 360° Case View">
                                                {loading360 ? <i className="fas fa-spinner fa-spin"></i> : <><i className="fas fa-eye"></i> 360°</>}
                                            </button>
                                            
                                            {/* Workflow Buttons */}
                                            {c.stage === 'reported' && <button onClick={() => {if(confirm('Begin review?')) apiCall('advance_case_stage', {case_id: c.id, stage: 'under_review'}).then(fetchCases);}} className="bg-primary hover:bg-primary-dark text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-soft hover:shadow-card">Begin Review</button>}
                                            {c.stage === 'under_review' && <button onClick={() => {setSelectedCase(c); setForm({}); setActiveModal('investigate');}} className="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1.5 rounded-md text-xs font-bold transition">Send to Investigate</button>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* MODALS FOR WORKFLOW */}
                    {activeModal === 'assign' && (
                        <Modal title={`Assign Committee to ${selectedCase?.case_no}`} onClose={() => setActiveModal(null)} submitText="Assign" onSubmit={submitModal}>
                            <div><label className="block text-xs font-bold text-gray-700 mb-1">Committee Member</label>
                                <select className="w-full border p-2.5 rounded-md text-sm" required value={form.committee_id || ''} onChange={e=>setForm({...form, committee_id:e.target.value})}>
                                    <option value="">-- Select Member --</option>
                                    {lookups.committee_members.map(m => <option key={m.id} value={m.id}>{m.full_name} ({m.staff_id})</option>)}
                                </select>
                            </div>
                        </Modal>
                    )}
                    {activeModal === 'investigate' && (
                        <Modal title={`Advance ${selectedCase?.case_no} to Investigation`} onClose={() => setActiveModal(null)} submitText="Advance" onSubmit={submitModal}>
                            <div><label className="block text-xs font-bold text-gray-700 mb-1">Review Notes</label>
                                <textarea required className="w-full border p-3 rounded-md text-sm h-32" placeholder="Initial findings..." value={form.note || ''} onChange={e=>setForm({...form, note:e.target.value})}></textarea>
                            </div>
                        </Modal>
                    )}

                    {/* --- THE 360° CASE VIEWER MODAL --- */}
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

        // --- DISCIPLINARY REPORT VIEW (Printable Notice Board with Filters) ---
        const ReportView = ({ lookups }) => {
            const [reports, setReports] = useState([]);
            const [loading, setLoading] = useState(false);
            const [filters, setFilters] = useState({
                academic_session: '',
                semester: '',
                faculty_id: '',
                department_id: '',
                timeframe: 'all'
            });

            const fetchReports = () => {
                setLoading(true);
                apiCall('get_sanctions_report', filters).then(res => setReports(res.data.data)).finally(() => setLoading(false));
            };

            useEffect(() => {
                fetchReports();
            }, []);

            return (
                <div className="space-y-6">
                    <div className="no-print flex flex-col gap-4 bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <div className="flex justify-between items-center">
                            <div>
                                <h3 className="font-bold text-lg text-gray-800">Official Disciplinary Reports</h3>
                                <p className="text-xs text-gray-500">Generate printable notice board lists for resolved cases based on specific criteria.</p>
                            </div>
                            <button onClick={() => window.print()} className="bg-primary hover:bg-accent-light text-white px-4 py-2 rounded-lg text-sm font-bold shadow-soft hover:shadow-card transition-all hover:-translate-y-0.5 flex items-center gap-2">
                                <i className="fas fa-print"></i> Print Notice Board Copy
                            </button>
                        </div>
                        
                        {/* Filter Section */}
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-3 pt-4 border-t border-gray-100">
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Session</label>
                                <input type="text" placeholder="e.g. 2024/2025" className="w-full border p-2 rounded-md text-sm outline-none focus:border-accent" value={filters.academic_session} onChange={e=>setFilters({...filters, academic_session:e.target.value})} />
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Semester</label>
                                <select className="w-full border p-2 rounded-md text-sm outline-none focus:border-accent" value={filters.semester} onChange={e=>setFilters({...filters, semester:e.target.value})}>
                                    <option value="">All Semesters</option>
                                    <option value="First">First Semester</option>
                                    <option value="Second">Second Semester</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Faculty</label>
                                <select className="w-full border p-2 rounded-md text-sm outline-none focus:border-accent" value={filters.faculty_id} onChange={e=>setFilters({...filters, faculty_id:e.target.value, department_id:''})}>
                                    <option value="">All Faculties</option>
                                    {lookups.faculties?.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Department</label>
                                <select className="w-full border p-2 rounded-md text-sm outline-none focus:border-accent" value={filters.department_id} onChange={e=>setFilters({...filters, department_id:e.target.value})} disabled={!filters.faculty_id}>
                                    <option value="">All Departments</option>
                                    {lookups.departments?.filter(d => d.faculty_id == filters.faculty_id).map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-gray-700 mb-1">Timeframe</label>
                                <select className="w-full border p-2 rounded-md text-sm outline-none focus:border-accent" value={filters.timeframe} onChange={e=>setFilters({...filters, timeframe:e.target.value})}>
                                    <option value="all">All Rulings to Date</option>
                                    <option value="recent">Recent (Last 30 Days)</option>
                                </select>
                            </div>
                            <div className="md:col-span-5 flex justify-end">
                                <button onClick={fetchReports} disabled={loading} className="bg-gray-800 hover:bg-gray-900 text-white px-6 py-2 rounded-md text-sm font-bold shadow transition flex items-center gap-2">
                                    {loading ? <i className="fas fa-spinner fa-spin"></i> : <i className="fas fa-filter"></i>} Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* PRINT CONTAINER */}
                    <div className="print-container bg-white p-8 rounded-xl shadow-sm border border-gray-200">
                        {/* Letterhead (Only visible nicely when printing or viewing this page) */}
                        <div className="text-center mb-8 border-b-2 border-primary pb-6">
                            <div className="w-16 h-16 bg-primary text-white rounded-full mx-auto flex items-center justify-center text-3xl mb-3 print-only no-print">
                                <i className="fas fa-university"></i>
                            </div>
                            <h1 className="text-2xl font-extrabold text-primary tracking-tight uppercase">Bayero University Kano</h1>
                            <h2 className="text-lg font-bold text-gray-800 tracking-widest mt-1">OFFICE OF THE REGISTRAR</h2>
                            <h3 className="text-md font-bold text-red-700 mt-4 underline">EXAMINATION DISCIPLINARY COMMITTEE</h3>
                            <p className="text-sm font-bold text-gray-600 mt-2 uppercase">
                                SUMMARY OF DECISIONS / SANCTIONS ON EXAMINATION MALPRACTICE
                                {filters.academic_session && ` (${filters.academic_session} SESSION)`}
                                {filters.semester && ` - ${filters.semester.toUpperCase()} SEMESTER`}
                            </p>
                        </div>

                        {loading ? <div className="text-center p-8 no-print"><i className="fas fa-spinner fa-spin text-2xl text-primary"></i></div> : reports.length === 0 ? <p className="text-center p-8 text-gray-500">No resolved cases found matching the criteria.</p> : (
                            <table className="w-full text-left text-sm border-collapse border border-gray-800">
                                <thead className="bg-gray-100">
                                    <tr>
                                        <th className="border border-gray-800 p-2 text-center w-10">S/N</th>
                                        <th className="border border-gray-800 p-2">Matric No</th>
                                        <th className="border border-gray-800 p-2">Name of Student</th>
                                        <th className="border border-gray-800 p-2">Course</th>
                                        <th className="border border-gray-800 p-2">Offence Committed</th>
                                        <th className="border border-gray-800 p-2">Committee Verdict / Sanction</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reports.map((r, i) => (
                                        <tr key={r.id}>
                                            <td className="border border-gray-800 p-2 text-center">{i + 1}</td>
                                            <td className="border border-gray-800 p-2 font-mono font-bold">{r.student_matric}</td>
                                            <td className="border border-gray-800 p-2">{r.student_name}</td>
                                            <td className="border border-gray-800 p-2">{r.course_code}</td>
                                            <td className="border border-gray-800 p-2 capitalize">{r.offence_type.replace('_', ' ')}</td>
                                            <td className="border border-gray-800 p-2 font-bold text-red-700">
                                                {r.sanction_type ? r.sanction_type.replace('_', ' ').toUpperCase() : 'RESOLVED'}
                                                {r.duration && <span className="block text-xs text-gray-600 font-normal mt-1">({r.duration})</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        
                        {/* Signatures for Print */}
                        <div className="mt-20 flex justify-between px-10 print-only">
                            <div className="text-center">
                                <div className="border-b border-black w-48 mb-2"></div>
                                <div className="text-sm font-bold">Chairman, EDC</div>
                            </div>
                            <div className="text-center">
                                <div className="border-b border-black w-48 mb-2"></div>
                                <div className="text-sm font-bold">Registrar</div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        };

        // --- Master Layout ---
        if (!user) {
            return (
                <div className="flex h-screen items-center justify-center bg-gray-50">
                    <div className="flex flex-col items-center gap-3 text-primary">
                        <i className="fas fa-circle-notch fa-spin text-3xl"></i>
                        <div className="text-sm font-bold text-gray-500 uppercase tracking-wide">Loading Exam Officer Console…</div>
                    </div>
                </div>
            );
        }

        return (
            <div className="flex h-screen bg-gray-50 overflow-hidden">
                <aside className="no-print w-64 bg-gradient-to-b from-primary to-primary-dark text-white flex flex-col h-full shadow-card z-20 relative">
                    <div className="h-16 flex items-center gap-3 px-6 font-display font-extrabold text-xl tracking-tight border-b border-white/10 bg-black/10">
                        <img src="assets/buk-logo.png" alt="BUK crest" className="w-9 h-9 rounded-lg bg-white p-0.5 shadow-soft shrink-0 object-contain" />
                        <span className="leading-tight">BUK<span className="text-accent-light ml-1">EIMS</span><small className="block text-[9px] font-semibold tracking-[0.12em] text-white/60">BAYERO UNIVERSITY KANO</small></span>
                    </div>
                    <div className="p-4 flex items-center gap-3 border-b border-white/10">
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-accent to-accent-light ring-2 ring-white/15 flex items-center justify-center font-bold text-lg shadow-soft shrink-0"><i className="fas fa-id-badge"></i></div>
                        <div>
                            <div className="text-sm font-bold truncate w-40">{user?.full_name}</div>
                            <div className="text-[10px] uppercase tracking-wider text-blue-200">Registry / Exam Officer</div>
                        </div>
                    </div>
                    <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
                        <div className="text-xs font-bold text-blue-300/50 uppercase tracking-wider mb-2 mt-2 px-2">Registry Tools</div>
                        <button onClick={()=>setView('dashboard')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='dashboard'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-chart-line w-5"></i> Dashboard</button>
                        <button onClick={()=>setView('incidents')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='incidents'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-inbox w-5"></i> Incident Triage</button>
                        <button onClick={()=>setView('cases')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='cases'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-gavel w-5"></i> Manage Cases</button>
                        
                        <div className="text-xs font-bold text-blue-300/50 uppercase tracking-wider mb-2 mt-6 px-2">Documentation</div>
                        <button onClick={()=>setView('reports')} className={`w-full text-left px-3.5 py-2.5 rounded-lg flex items-center gap-3 transition-all text-sm border-l-[3px] ${view==='reports'?'bg-white/10 text-white font-bold border-accent-light shadow-inner':'border-transparent text-blue-100/80 hover:bg-white/5 hover:text-white'}`}><i className="fas fa-print w-5"></i> Notice Board Reports</button>
                    </nav>
                </aside>

                <div className="flex-1 flex flex-col min-w-0">
                    <header className="no-print h-16 bg-white/95 backdrop-blur-sm shadow-soft flex items-center justify-between px-8 z-10 border-b border-gray-100">
                        <h1 className="text-xl font-display font-extrabold text-gray-800 capitalize tracking-tight">
                            {view === 'dashboard' ? 'Overview' : view === 'incidents' ? 'Incident Triage' : view === 'cases' ? 'Case Management' : 'Disciplinary Reports'}
                        </h1>
                        <div className="flex items-center gap-6">
                            <button onClick={logout} className="text-red-500 hover:bg-red-50 px-3 py-1.5 rounded transition text-sm font-bold flex items-center gap-2">
                                <i className="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </div>
                    </header>
                    <main className="flex-1 overflow-y-auto p-8">
                        {view === 'dashboard' ? <DashboardView/> : 
                         view === 'incidents' ? <IncidentsView/> : 
                         view === 'cases' ? <CasesView/> :
                         <ReportView lookups={lookups} />}
                    </main>
                </div>
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<EOApp />);
</script>
</body>
</html>
