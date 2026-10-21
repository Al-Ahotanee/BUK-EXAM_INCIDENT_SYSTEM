const API = './api.php';
const {
  useState,
  useEffect,
  useMemo
} = React;

const iconMap = {
  overview: 'fa-grid-2',
  work: 'fa-list-check',
  incidents: 'fa-file-circle-exclamation',
  cases: 'fa-folder-open',
  hearings: 'fa-calendar-days',
  senate: 'fa-landmark',
  lockers: 'fa-box-archive',
  intelligence: 'fa-shield-halved',
  calendar: 'fa-calendar-check',
  reports: 'fa-chart-line',
  announcements: 'fa-bullhorn',
  settings: 'fa-sliders',
  drafts: 'fa-file-pen',
  operations: 'fa-diagram-project',
  users: 'fa-user-shield'
};

function App() {
  const [user, setUser] = useState(null),
    [csrf, setCsrf] = useState(''),
    [data, setData] = useState(null),
    [page, setPage] = useState('overview'),
    [toast, setToast] = useState(''),
    [mobile, setMobile] = useState(false),
    [query, setQuery] = useState(''),
    [results, setResults] = useState([]),
    [caseId, setCaseId] = useState(null),
    [caseData, setCaseData] = useState(null),
    [draftPayload, setDraftPayload] = useState(null),
    [panicOpen, setPanicOpen] = useState(false),
    [alerts, setAlerts] = useState([]),
    [lookups, setLookups] = useState({
      departments: [],
      faculties: [],
      courses: [],
      committee_members: [],
      staff_members: [],
      sessions: [],
      venues: [],
      students: [],
      schedules: []
    });

  const api = async (action, payload = {}, method = 'post', config = {}) => {
    const r = await axios({
      url: `${API}?action=${action}`,
      method,
      data: payload,
      headers: {
        'X-Csrf-Token': csrf,
        ...(config.headers || {})
      },
      ...config
    });
    return r.data;
  };

  const notify = m => {
    setToast(m);
    setTimeout(() => setToast(''), 3500);
  };

  const fetchAlerts = () => api('get_security_alerts', {}, 'get').then(r => setAlerts(r.data || [])).catch(() => {});

  useEffect(() => {
    axios.post(`${API}?action=check_auth`).then(async r => {
      if (!r.data.isAuthenticated) {
        location.href = 'index.php';
        return;
      }
      setUser(r.data.user);
      setCsrf(r.data.csrf_token);
      const [w, l, al] = await Promise.all([
        axios.post(`${API}?action=get_workspace_data`),
        axios.post(`${API}?action=get_lookups`),
        axios.get(`${API}?action=get_security_alerts`).catch(() => ({ data: { data: [] } }))
      ]);
      setData(w.data.data);
      setLookups(l.data.data || {});
      setAlerts(al.data.data || []);
    }).catch(() => location.href = 'index.php');
  }, []);

  useEffect(() => {
    if (query.trim().length > 1) {
      const t = setTimeout(() => api('get_global_search', {
        q: query
      }).then(r => setResults(r.data || [])).catch(() => {}), 250);
      return () => clearTimeout(t);
    }
    setResults([]);
  }, [query]);

  const refresh = async () => {
    const [r] = await Promise.all([
      api('get_workspace_data'),
      fetchAlerts()
    ]);
    setData(r.data);
  };

  const go = p => {
    if (p === 'operations') { 
      location.href = 'operations.php'; 
      return; 
    }
    setPage(p);
    setMobile(false);
    if (p !== 'case') {
      setCaseId(null);
      setCaseData(null);
    }
  };

  const openCase = async id => {
    try {
      const r = await api('get_case_timeline', {
        case_id: id
      });
      setCaseId(id);
      setCaseData(r.data);
      setPage('case');
    } catch (e) {
      notify(e.response?.data?.message || 'Unable to open case dossier');
    }
  };

  const loadDraft = payload => {
    setDraftPayload(payload);
    go('incidents');
    notify('Draft form data restored into incident report');
  };

  if (!user || !data) {
    return React.createElement("div", {
      style: {
        minHeight: '100vh',
        display: 'grid',
        placeItems: 'center',
        background: '#ffffff',
        color: '#0b5c8e'
      }
    }, React.createElement("i", {
      className: "fas fa-circle-notch fa-spin fa-2x"
    }));
  }

  const roleLabel = {
    admin: 'System Administrator',
    exam_officer: 'Examination Officer',
    invigilator: 'Invigilator',
    hod: 'HOD / Dean',
    committee: 'Committee Member'
  }[user.role] || user.role;

  const nav = [
    ['overview', 'Overview'], 
    ['work', 'My Work'], 
    ['incidents', 'Incidents'], 
    ['cases', 'Cases'], 
    ['hearings', 'Hearings'], 
    ['senate', 'Senate Memos'],
    ['lockers', 'Evidence Safe'],
    ['intelligence', 'Intelligence & Risk'],
    ['calendar', 'Calendar'], 
    ['reports', 'Reports'], 
    ['operations', 'Operations']
  ];
  if (['invigilator', 'exam_officer', 'admin'].includes(user.role)) nav.push(['drafts', 'Drafts']);
  if (user.role === 'admin') nav.push(['announcements', 'Announcements'], ['users', 'User management'], ['settings', 'Settings']);

  return React.createElement("div", {
    className: "modern-shell"
  }, React.createElement("aside", {
    className: `modern-sidebar ${mobile ? 'open' : ''}`
  }, React.createElement("div", {
    className: "brand-lockup"
  }, React.createElement("img", {
    src: "assets/buk-logo.png",
    alt: "Bayero University Kano crest"
  }), React.createElement("div", null, 
    React.createElement("strong", null, "BUK", React.createElement("span", {
      style: { color: 'var(--buk-gold-light)' }
    }, " EIMS")), 
    React.createElement("small", null, "BAYERO UNIVERSITY KANO")
  )), React.createElement("nav", {
    className: "modern-nav"
  }, React.createElement("div", {
    className: "nav-label"
  }, "Workspace"), nav.map(([id, label]) => React.createElement("button", {
    key: id,
    className: page === id ? 'active' : '',
    onClick: () => go(id)
  }, React.createElement("i", {
    className: `fas ${iconMap[id] || 'fa-circle'}`
  }), label)), React.createElement("div", {
    className: "nav-label"
  }, "Account"), React.createElement("button", {
    className: page === 'settings' ? 'active' : '',
    onClick: () => go('settings')
  }, React.createElement("i", {
    className: "fas fa-user-gear"
  }), "Profile & preferences")), React.createElement("div", {
    className: "sidebar-user"
  }, React.createElement("div", {
    className: "avatar"
  }, user.full_name?.split(' ').map(x => x[0]).join('').slice(0, 2)), React.createElement("div", {
    style: { minWidth: 0 }
  }, React.createElement("div", {
    style: {
      fontSize: 12,
      fontWeight: 800,
      whiteSpace: 'nowrap',
      overflow: 'hidden',
      textOverflow: 'ellipsis'
    }
  }, user.full_name), React.createElement("div", {
    style: {
      fontSize: 10,
      color: 'rgba(255,255,255,.55)',
      marginTop: 3
    }
  }, roleLabel)))), React.createElement("main", {
    className: "main-canvas"
  }, React.createElement("header", {
    className: "modern-topbar no-print"
  }, React.createElement("div", {
    style: { display: 'flex', alignItems: 'center', gap: 14 }
  }, React.createElement("button", {
    className: "icon-button mobile-menu",
    onClick: () => setMobile(!mobile),
    "aria-label": "Toggle navigation"
  }, React.createElement("i", { className: "fas fa-bars" })), React.createElement("div", null, 
    React.createElement("div", { className: "crumb" }, "BUK EIMS / ", page === 'overview' ? 'workspace' : page), 
    React.createElement("div", { className: "page-heading" }, 
      page === 'overview' ? `Good day, ${user.full_name?.split(' ')[0]}` : 
      page === 'case' ? 'Case dossier' : page.replace('_', ' ')
    )
  )), React.createElement("div", {
    className: "top-actions"
  }, React.createElement("div", {
    className: "search-box"
  }, React.createElement("i", {
    className: "fas fa-search",
    style: { color: 'var(--muted)' }
  }), React.createElement("input", {
    value: query,
    onChange: e => setQuery(e.target.value),
    placeholder: "Search cases, incidents, matric..."
  }), results.length > 0 && React.createElement("div", {
    style: {
      position: 'absolute',
      top: 62,
      right: 140,
      width: 350,
      background: '#fff',
      border: '1px solid var(--line)',
      borderRadius: 15,
      boxShadow: 'var(--shadow)',
      padding: 8,
      zIndex: 30
    }
  }, results.map(x => React.createElement("button", {
    key: x.result_type + x.id,
    onClick: () => {
      setQuery('');
      x.result_type === 'case' ? openCase(x.id) : go('incidents');
    },
    style: {
      display: 'block',
      width: '100%',
      textAlign: 'left',
      background: 'transparent',
      border: 0,
      padding: 10,
      borderRadius: 9,
      cursor: 'pointer'
    }
  }, React.createElement("strong", {
    style: { fontSize: 12 }
  }, x.ref), React.createElement("div", {
    style: { fontSize: 11, color: 'var(--muted)', marginTop: 3 }
  }, x.title, " · ", x.meta))))), React.createElement("button", {
    className: "panic-btn ghost-button",
    onClick: () => setPanicOpen(true),
    title: "Exam Hall Emergency Security Dispatch"
  }, React.createElement("i", { className: "fas fa-triangle-exclamation" }), React.createElement("span", null, "Hall Alert")),
  React.createElement("button", {
    className: "icon-button",
    onClick: () => {
      go('overview');
      document.getElementById('notifications')?.scrollIntoView({ behavior: 'smooth' });
    },
    title: "Notifications"
  }, React.createElement("i", {
    className: "far fa-bell"
  }), data.notifications?.some(n => !n.is_read) && React.createElement("span", {
    className: "notification-dot"
  })), React.createElement("button", {
    className: "ghost-button",
    onClick: async () => {
      await axios.post(`${API}?action=logout`);
      location.href = 'index.php';
    }
  }, React.createElement("i", {
    className: "fas fa-arrow-right-from-bracket"
  }), React.createElement("span", null, "Sign out")))), React.createElement("div", {
    className: "workspace-content"
  }, 
    // Emergency Hall Security Alert Broadcast Banner
    alerts.filter(a => a.status === 'active').slice(0, 1).map(alert => React.createElement("div", {
      className: "panic-banner",
      key: 'alert-' + alert.id
    }, React.createElement("div", { style: { display: 'flex', alignItems: 'center', gap: 10 } },
      React.createElement("i", { className: "fas fa-bell fa-beat-fade", style: { color: '#e11d48', fontSize: 18 } }),
      React.createElement("div", null,
        React.createElement("strong", null, `EMERGENCY HALL ALERT: ${alert.venue} (${(alert.urgency || '').toUpperCase()})`),
        React.createElement("p", null, `${alert.reason} · Invigilator: ${alert.invigilator_name || 'Staff'}`)
      )
    ), ['admin', 'exam_officer'].includes(user.role) && React.createElement("button", {
      className: "primary-button",
      style: { minHeight: 32, fontSize: 11 },
      onClick: async () => {
        await api('resolve_security_alert', { alert_id: alert.id, resolution_notes: 'Resolved by Chief Invigilator/Security Command' });
        notify('Security alert marked as resolved');
        fetchAlerts();
      }
    }, "Mark Resolved"))),

    page === 'overview' && React.createElement(Overview, { data, user, go, openCase, notify, api, refresh }),
    page === 'work' && React.createElement(Tasks, { data, api, refresh, notify }),
    page === 'incidents' && React.createElement(Incidents, { api, openCase, user, lookups, notify, draftPayload, clearDraft: () => setDraftPayload(null) }),
    page === 'cases' && React.createElement(Cases, { api, openCase, user }),
    page === 'case' && React.createElement(CaseWorkspace, { 
      data: caseData, 
      user, 
      api, 
      notify, 
      onBack: () => go('cases'), 
      refresh: async () => openCase(caseId),
      lookups
    }),
    page === 'hearings' && React.createElement(Hearings, { data, lookups, api, notify, openCase }),
    page === 'senate' && React.createElement(SenateMemos, { api, notify, user, lookups, openCase }),
    page === 'lockers' && React.createElement(EvidenceLocker, { api, notify, user, lookups, openCase }),
    page === 'intelligence' && React.createElement(Intelligence, { api, notify, user, lookups, openCase }),
    page === 'calendar' && React.createElement(Calendar, { api }),
    page === 'reports' && React.createElement(Reports, { api }),
    page === 'drafts' && React.createElement(Drafts, { api, notify, onContinueDraft: loadDraft }),
    page === 'announcements' && React.createElement(Announcements, { api, notify }),
    page === 'settings' && React.createElement(Settings, { api, user, notify }),
    page === 'users' && user.role === 'admin' && React.createElement(UserManagement, { api, user, lookups, notify })
  )), toast && React.createElement("div", {
    className: "toast"
  }, React.createElement("i", {
    className: "fas fa-circle-check",
    style: { color: 'var(--buk-gold-light)', marginRight: 8 }
  }), toast),
  panicOpen && React.createElement(PanicAlertModal, {
    close: () => setPanicOpen(false),
    api,
    notify,
    lookups,
    onDispatched: () => { setPanicOpen(false); fetchAlerts(); }
  }));
}

function Modal({ title, close, children, width = '760px', subtitle = null }) {
  return React.createElement("div", {
    className: "modal-backdrop",
    onClick: e => {
      if (e.target === e.currentTarget && close) close();
    }
  }, React.createElement("div", {
    className: "modern-modal",
    style: { maxWidth: width }
  }, React.createElement("div", {
    className: "modal-head"
  }, React.createElement("div", null,
    React.createElement("h3", null, title),
    subtitle && React.createElement("div", { className: "panel-sub" }, subtitle)
  ), close && React.createElement("button", {
    className: "icon-button",
    onClick: close,
    "aria-label": "Close modal"
  }, React.createElement("i", { className: "fas fa-times" }))),
  React.createElement("div", { className: "modal-body" }, children)
  ));
}

function SectionHead({ eyebrow, title, sub, action }) {
  return React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'flex-end',
      gap: 20,
      marginBottom: 17
    }
  }, React.createElement("div", null, 
    React.createElement("div", {
      className: "crumb",
      style: { textTransform: 'uppercase', letterSpacing: '.12em' }
    }, eyebrow), 
    React.createElement("h2", {
      style: { margin: '5px 0 0', fontSize: 22 }
    }, title), 
    sub && React.createElement("div", {
      style: { fontSize: 12, color: 'var(--muted)', marginTop: 6 }
    }, sub)
  ), action);
}

function Overview({ data, user, go, openCase, notify, api, refresh }) {
  const s = data.stats || {};
  return React.createElement(React.Fragment, null, 
    React.createElement("div", {
      className: "hero-strip"
    }, React.createElement("div", {
      style: { position: 'relative', zIndex: 1 }
    }, React.createElement("div", {
      style: {
        fontSize: 11,
        fontWeight: 800,
        letterSpacing: '.12em',
        textTransform: 'uppercase',
        color: 'var(--buk-gold-light)'
      }
    }, "Institutional integrity workspace"), 
    React.createElement("h2", null, "One clear view of every action that matters."), 
    React.createElement("p", null, "Manage reports, review tasks, hearings, communications and case decisions with a modern BUK workflow."), 
    React.createElement("div", {
      className: "quick-actions"
    }, React.createElement("button", {
      className: "primary-button",
      onClick: () => go(user.role === 'invigilator' ? 'drafts' : 'work')
    }, React.createElement("i", { className: "fas fa-bolt" }), 
      user.role === 'invigilator' ? 'Continue a report' : 'Open my work'
    ), React.createElement("button", {
      className: "ghost-button",
      onClick: () => go('incidents')
    }, React.createElement("i", { className: "fas fa-file-circle-exclamation" }), "Incident registry"),
    ['admin', 'exam_officer'].includes(user.role) && React.createElement("button", {
      className: "gold-button",
      onClick: () => go('operations')
    }, React.createElement("i", { className: "fas fa-diagram-project" }), "Operations console")
  ))), React.createElement("div", {
    className: "stat-grid"
  }, [['incidents', 'Incidents', 'fa-file-circle-exclamation'], ['open_cases', 'Open cases', 'fa-folder-open'], ['resolved_cases', 'Resolved cases', 'fa-circle-check'], ['overdue_tasks', 'Overdue tasks', 'fa-clock']].map(([key, label, ico]) => React.createElement("div", {
    className: "stat-card",
    key: key
  }, React.createElement("div", {
    className: "stat-top"
  }, label, React.createElement("div", { className: "stat-icon" }, React.createElement("i", { className: `fas ${ico}` }))), 
  React.createElement("div", { className: "stat-value" }, s[key] ?? 0), 
  React.createElement("div", { className: "stat-meta" }, "System metric")))), 
  React.createElement("div", {
    className: "grid-2"
  }, React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Institutional announcements")), React.createElement("div", {
    className: "panel-body"
  }, data.announcements?.length ? data.announcements.map(a => React.createElement("div", {
    className: "list-row",
    key: a.id
  }, React.createElement("div", null, 
    React.createElement("div", { className: "row-title" }, a.title), 
    React.createElement("div", { className: "row-meta", style: { marginTop: 4 } }, a.body)
  ), React.createElement("span", {
    className: "row-meta"
  }, new Date(a.created_at).toLocaleDateString()))) : React.createElement("div", {
    className: "empty-state"
  }, "No announcements."))), React.createElement("div", {
    className: "panel",
    id: "notifications"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Recent notifications")), React.createElement("div", {
    className: "panel-body"
  }, data.notifications?.length ? data.notifications.map(n => React.createElement("div", {
    className: "list-row",
    key: n.id
  }, React.createElement("div", null, 
    React.createElement("div", { className: "row-title" }, n.title), 
    React.createElement("div", { className: "row-meta" }, n.message)
  ), !n.is_read && React.createElement("span", {
    className: "status-pill open"
  }, "New"))) : React.createElement("div", {
    className: "empty-state"
  }, "No notifications.")))));
}

function Tasks({ data, api, refresh, notify }) {
  const [tasks, setTasks] = useState(data.tasks || []);
  useEffect(() => {
    api('get_tasks').then(r => setTasks(r.data || []));
  }, []);
  const update = async (t, status) => {
    await api('update_task_status', {
      task_id: t.id,
      status
    });
    setTasks(x => x.map(y => y.id === t.id ? { ...y, status } : y));
    notify('Task updated successfully');
  };
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "My work",
    title: "Task inbox",
    sub: "A focused list of the actions currently assigned to you."
  }), React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Assigned tasks ", React.createElement("span", {
    className: "status-pill open",
    style: { marginLeft: 8 }
  }, tasks.filter(t => t.status !== 'completed').length, " open")), React.createElement("button", {
    className: "ghost-button",
    onClick: refresh
  }, React.createElement("i", { className: "fas fa-rotate" }), "Refresh")), React.createElement("div", {
    className: "panel-body"
  }, tasks.length ? React.createElement("table", {
    className: "modern-table"
  }, React.createElement("thead", null, React.createElement("tr", null, 
    React.createElement("th", null, "Task"), 
    React.createElement("th", null, "Reference"), 
    React.createElement("th", null, "Priority"), 
    React.createElement("th", null, "Due"), 
    React.createElement("th", null, "Status"), 
    React.createElement("th", null, "Action")
  )), React.createElement("tbody", null, tasks.map(t => React.createElement("tr", {
    key: t.id
  }, React.createElement("td", null, 
    React.createElement("strong", null, t.title), 
    React.createElement("div", { className: "row-meta" }, t.description)
  ), React.createElement("td", null, t.case_no || t.reference_no || '—'), 
  React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${t.priority === 'urgent' ? 'urgent' : t.priority}`
  }, t.priority)), React.createElement("td", null, t.due_date ? new Date(t.due_date).toLocaleDateString() : '—'), 
  React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${t.status}`
  }, t.status.replace('_', ' '))), React.createElement("td", null, React.createElement("select", {
    value: t.status,
    onChange: e => update(t, e.target.value),
    style: {
      border: '1px solid var(--line)',
      borderRadius: 8,
      padding: 6,
      fontSize: 11
    }
  }, React.createElement("option", { value: "open" }, "Open"), 
     React.createElement("option", { value: "in_progress" }, "In progress"), 
     React.createElement("option", { value: "completed" }, "Complete"), 
     React.createElement("option", { value: "dismissed" }, "Dismiss")
  )))))) : React.createElement("div", {
    className: "empty-state"
  }, React.createElement("i", { className: "fas fa-check-double" }), React.createElement("div", null, "No assigned tasks.")))));
}

function Incidents({ api, openCase, user, lookups, notify, draftPayload, clearDraft }) {
  const [rows, setRows] = useState([]),
    [q, setQ] = useState(''),
    [show, setShow] = useState(false),
    [step, setStep] = useState(1),
    [form, setForm] = useState({}),
    [inspecting, setInspecting] = useState(null),
    [inspectData, setInspectData] = useState(null),
    [loadingInspect, setLoadingInspect] = useState(false),
    [radarData, setRadarData] = useState(null),
    [showRadarModal, setShowRadarModal] = useState(false);

  useEffect(() => {
    api('get_incidents').then(r => setRows(r.data || []));
  }, []);

  useEffect(() => {
    if (draftPayload) {
      setForm(draftPayload);
      setShow(true);
      setStep(draftPayload.step || 1);
      if (clearDraft) clearDraft();
    }
  }, [draftPayload]);

  const filtered = rows.filter(r => JSON.stringify(r).toLowerCase().includes(q.toLowerCase()));
  const set = (k, v) => setForm({ ...form, [k]: v });

  const viewIncident = async id => {
    setInspecting(id);
    setLoadingInspect(true);
    try {
      const res = await api('get_incident_details', { incident_id: id });
      setInspectData(res.data);
    } catch (e) {
      notify('Unable to load incident record details');
      setInspecting(null);
    } finally {
      setLoadingInspect(false);
    }
  };

  const handleMatricChange = async matric => {
    set('student_matric', matric);
    const found = lookups.students?.find(s => s.matric_no.toLowerCase() === matric.trim().toLowerCase());
    if (found) {
      setForm(prev => ({
        ...prev,
        student_matric: matric,
        student_name: found.full_name,
        student_dept_id: found.department_id,
        student_level: found.level
      }));
    }
    if (matric.trim().length >= 4) {
      try {
        const r = await api('get_student_disciplinary_history', { matric_no: matric.trim() });
        setRadarData(r.data);
      } catch (e) {
        setRadarData(null);
      }
    } else {
      setRadarData(null);
    }
  };

  const handleScheduleSelect = schedId => {
    const sched = lookups.schedules?.find(s => String(s.id) === String(schedId));
    if (!sched) return;
    setForm(prev => ({
      ...prev,
      course_id: sched.course_id || prev.course_id,
      venue: sched.venue || prev.venue,
      exam_date: sched.exam_date || prev.exam_date,
      exam_time: sched.session_time ? (sched.session_time.includes(':') ? sched.session_time.slice(0, 5) : '09:00') : prev.exam_time,
      session_time: sched.session_time || prev.session_time,
      student_dept_id: sched.department_id || prev.student_dept_id,
      academic_session: sched.academic_session || prev.academic_session
    }));
    notify(`Timetable auto-fill applied: ${sched.course_code} at ${sched.venue}`);
  };

  const submit = async e => {
    e.preventDefault();
    const fd = new FormData();
    Object.entries(form).forEach(([k, v]) => fd.append(k, v ?? ''));
    const file = e.currentTarget.querySelector('input[type="file"]')?.files?.[0];
    if (file) fd.append('evidence', file);
    
    try {
      const r = await api('report_incident', fd);
      notify(`Incident ${r.reference_no || ''} submitted successfully`);
      setShow(false);
      setStep(1);
      setForm({});
      api('get_incidents').then(res => setRows(res.data || []));
    } catch (err) {
      notify(err.response?.data?.message || 'Error submitting incident report');
    }
  };

  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Records",
    title: "Incident registry",
    sub: "Search, review and act on examination incident reports.",
    action: ['invigilator', 'exam_officer', 'admin'].includes(user.role) && React.createElement("button", {
      className: "primary-button",
      onClick: () => { setForm({}); setStep(1); setShow(true); }
    }, React.createElement("i", { className: "fas fa-plus" }), "New incident")
  }), React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "search-box",
    style: { minWidth: 320 }
  }, React.createElement("i", { className: "fas fa-filter" }), React.createElement("input", {
    value: q,
    onChange: e => setQ(e.target.value),
    placeholder: "Filter by reference, student, venue..."
  })), React.createElement("div", {
    className: "row-meta"
  }, filtered.length, " records")), React.createElement("div", {
    style: { overflowX: 'auto' }
  }, React.createElement("table", {
    className: "modern-table"
  }, React.createElement("thead", null, React.createElement("tr", null, 
    React.createElement("th", null, "Reference"), 
    React.createElement("th", null, "Student"), 
    React.createElement("th", null, "Course"), 
    React.createElement("th", null, "Offence"), 
    React.createElement("th", null, "Status"), 
    React.createElement("th", null, "Submitted"), 
    React.createElement("th", null, "Action")
  )), React.createElement("tbody", null, filtered.map(r => React.createElement("tr", {
    key: r.id
  }, React.createElement("td", null, React.createElement("strong", null, r.reference_no)), 
  React.createElement("td", null, r.student_name, React.createElement("div", {
    className: "row-meta"
  }, r.student_matric)), React.createElement("td", null, r.course_code), 
  React.createElement("td", null, (r.offence_type || '').replace('_', ' ')), 
  React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${r.status}`
  }, r.status.replace('_', ' '))), React.createElement("td", null, new Date(r.created_at).toLocaleDateString()), 
  React.createElement("td", null, React.createElement("button", {
    className: "ghost-button",
    onClick: () => viewIncident(r.id)
  }, React.createElement("i", { className: "fas fa-eye" }), " Inspect")))))), !filtered.length && React.createElement("div", {
    className: "empty-state"
  }, "No matching incidents found."))),

  // Incident Inspection Modal
  inspecting && React.createElement(Modal, {
    title: inspectData ? `Incident Record: ${inspectData.incident.reference_no}` : "Loading Record...",
    subtitle: inspectData ? `${inspectData.incident.student_name} (${inspectData.incident.student_matric})` : null,
    close: () => { setInspecting(null); setInspectData(null); },
    width: "740px"
  }, loadingInspect ? React.createElement("div", { className: "empty-state" }, React.createElement("i", { className: "fas fa-circle-notch fa-spin" }), " Loading details...") : inspectData && React.createElement("div", null,
    React.createElement("div", { className: "form-grid", style: { marginBottom: 16 } },
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Course"), React.createElement("strong", { className: "row-title" }, `${inspectData.incident.course_code} — ${inspectData.incident.course_title || ''}`)),
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Exam Venue & Date"), React.createElement("strong", { className: "row-title" }, `${inspectData.incident.venue} · ${inspectData.incident.exam_date} ${inspectData.incident.exam_time}`)),
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Semester & Session"), React.createElement("strong", { className: "row-title" }, `${inspectData.incident.semester} Semester · ${inspectData.incident.academic_session}`)),
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Reported By"), React.createElement("strong", { className: "row-title" }, inspectData.incident.reporter_name || 'Staff Invigilator'))
    ),
    React.createElement("div", { className: "panel", style: { background: '#ffffff', border: '1px solid var(--line)', boxShadow: 'none', marginBottom: 16 } },
      React.createElement("div", { className: "panel-body" },
        React.createElement("div", { className: "row-title", style: { marginBottom: 6 } }, `Alleged Offence: ${(inspectData.incident.offence_type || '').replace('_', ' ').toUpperCase()}`),
        React.createElement("p", { style: { fontSize: 13, lineHeight: 1.6, color: '#334155', whiteSpace: 'pre-wrap' } }, inspectData.incident.description)
      )
    ),
    React.createElement("div", { style: { marginBottom: 16 } },
      React.createElement("strong", { style: { fontSize: 12, textTransform: 'uppercase', letterSpacing: '.08em', color: 'var(--muted)' } }, "Attached Evidence"),
      inspectData.evidence?.length ? inspectData.evidence.map(ev => React.createElement("div", {
        className: "evidence-card",
        key: ev.id
      }, React.createElement("div", null,
        React.createElement("strong", null, React.createElement("i", { className: "fas fa-file-shield", style: { color: 'var(--buk-blue)', marginRight: 8 } }), ev.original_name),
        React.createElement("small", null, `${(ev.file_size / 1024).toFixed(1)} KB · Uploaded ${new Date(ev.uploaded_at).toLocaleDateString()}`)
      ), React.createElement("button", {
        className: "primary-button",
        style: { minHeight: 32, fontSize: 11, padding: '0 10px' },
        onClick: () => window.open(`api.php?action=download_evidence&id=${ev.id}`, '_blank')
      }, React.createElement("i", { className: "fas fa-download" }), " Download"))) : React.createElement("div", { className: "row-meta", style: { marginTop: 6 } }, "No physical evidence files attached.")
    ),
    inspectData.case && React.createElement("div", { style: { display: 'flex', justifyContent: 'flex-end', gap: 10, borderTop: '1px solid var(--line)', paddingTop: 14 } },
      React.createElement("button", {
        className: "primary-button",
        onClick: () => { setInspecting(null); openCase(inspectData.case.id); }
      }, React.createElement("i", { className: "fas fa-folder-open" }), ` Open Case Dossier (${inspectData.case.case_no})`)
    )
  )),

  // New Incident Report Wizard
  show && React.createElement("div", {
    className: "modal-backdrop"
  }, React.createElement("div", {
    className: "modern-modal"
  }, React.createElement("div", {
    className: "modal-head"
  }, React.createElement("div", null, 
    React.createElement("h3", null, "New incident report"), 
    React.createElement("div", { className: "panel-sub" }, "Step ", step, " of 3 · Save a clear, complete record.")
  ), React.createElement("button", {
    className: "icon-button",
    onClick: () => setShow(false)
  }, React.createElement("i", { className: "fas fa-times" }))), 
  React.createElement("form", {
    onSubmit: submit
  }, React.createElement("div", {
    className: "modal-body"
  }, step === 1 && React.createElement("div", {
    className: "form-grid"
  }, React.createElement(Field, {
    label: "Auto-fill from exam timetable (optional)",
    style: { gridColumn: '1/-1' }
  }, React.createElement("select", {
    onChange: e => handleScheduleSelect(e.target.value),
    defaultValue: ""
  }, React.createElement("option", { value: "" }, "-- Select scheduled examination to prefill venue, course, and date --"),
     lookups.schedules?.map(sc => React.createElement("option", { key: sc.id, value: sc.id },
       `${sc.course_code} — ${sc.course_title || ''} · ${sc.venue} · ${sc.exam_date} (${sc.session_time || ''})`
     ))
  )),
  radarData?.total_incidents > 0 && React.createElement("div", {
    style: { gridColumn: '1/-1', background: '#ffffff', border: '1px solid #fecdd3', borderLeft: '4px solid #e11d48', borderRadius: 12, padding: 12, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }
  }, React.createElement("div", null,
    React.createElement("strong", { style: { color: '#9f1239', fontSize: 12 } }, "⚠️ REPEAT OFFENDER RADAR: ", radarData.risk_badge),
    React.createElement("p", { style: { margin: '2px 0 0', fontSize: 11, color: '#881337' } }, `Student has ${radarData.total_incidents} prior examination incident(s) and ${radarData.active_sanctions} recorded sanction(s) on file.`)
  ), React.createElement("button", {
    type: "button",
    className: "ghost-button",
    style: { minHeight: 30, fontSize: 11, borderColor: '#fda4af', background: '#ffffff' },
    onClick: () => setShowRadarModal(true)
  }, React.createElement("i", { className: "fas fa-history" }), " View Dossier")),
  React.createElement(Field, {
    label: "Matriculation number"
  }, React.createElement("input", {
    required: true,
    value: form.student_matric || '',
    onChange: e => handleMatricChange(e.target.value),
    placeholder: "e.g. CST/21/COM/00142"
  })), React.createElement(Field, {
    label: "Student name"
  }, React.createElement("input", {
    required: true,
    value: form.student_name || '',
    onChange: e => set('student_name', e.target.value),
    placeholder: "Full student name"
  })), React.createElement(Field, {
    label: "Course"
  }, React.createElement("select", {
    required: true,
    value: form.course_id || '',
    onChange: e => set('course_id', e.target.value)
  }, React.createElement("option", { value: "" }, "Select course"), 
     lookups.courses?.map(c => React.createElement("option", {
       value: c.id,
       key: c.id
     }, `${c.code} — ${c.title}`))
  )), React.createElement(Field, {
    label: "Department"
  }, React.createElement("select", {
    required: true,
    value: form.student_dept_id || '',
    onChange: e => set('student_dept_id', e.target.value)
  }, React.createElement("option", { value: "" }, "Select department"), 
     lookups.departments?.map(d => React.createElement("option", {
       value: d.id,
       key: d.id
     }, d.name))
  )), React.createElement(Field, {
    label: "Student level"
  }, React.createElement("select", {
    required: true,
    value: form.student_level || '100',
    onChange: e => set('student_level', e.target.value)
  }, ['100', '200', '300', '400', '500'].map(x => React.createElement("option", { key: x, value: x }, x)))), 
  React.createElement(Field, {
    label: "Exam date"
  }, React.createElement("input", {
    type: "date",
    required: true,
    value: form.exam_date || '',
    onChange: e => set('exam_date', e.target.value)
  })), React.createElement(Field, {
    label: "Exam time"
  }, React.createElement("input", {
    type: "time",
    required: true,
    value: form.exam_time || '',
    onChange: e => set('exam_time', e.target.value)
  }))), step === 2 && React.createElement("div", {
    className: "form-grid"
  }, React.createElement(Field, {
    label: "Venue"
  }, React.createElement("input", {
    required: true,
    value: form.venue || '',
    onChange: e => set('venue', e.target.value),
    placeholder: "e.g. Examination Hall A"
  })), React.createElement(Field, {
    label: "Semester"
  }, React.createElement("select", {
    value: form.semester || 'First',
    onChange: e => set('semester', e.target.value)
  }, React.createElement("option", null, "First"), React.createElement("option", null, "Second"))), 
  React.createElement(Field, {
    label: "Academic session"
  }, React.createElement("input", {
    required: true,
    value: form.academic_session || '2025/2026',
    onChange: e => set('academic_session', e.target.value)
  })), React.createElement(Field, {
    label: "Offence type"
  }, React.createElement("select", {
    value: form.offence_type || 'other',
    onChange: e => set('offence_type', e.target.value)
  }, ['foreign_material', 'electronic_device', 'impersonation', 'collusion', 'assault', 'misconduct', 'other'].map(x => React.createElement("option", {
    key: x,
    value: x
  }, x.replace('_', ' '))))), React.createElement("div", {
    className: "field",
    style: { gridColumn: '1/-1' }
  }, React.createElement("label", null, "Incident narrative"), React.createElement("textarea", {
    required: true,
    value: form.description || '',
    onChange: e => set('description', e.target.value),
    placeholder: "Describe clearly what happened, what items were confiscated, and what was observed."
  }))), step === 3 && React.createElement("div", null, React.createElement("div", {
    className: "panel",
    style: { background: '#ffffff', border: '1px solid var(--line)', boxShadow: 'none' }
  }, React.createElement("div", {
    className: "panel-body"
  }, React.createElement("div", {
    className: "row-title"
  }, "Review before submission"), 
  React.createElement("p", { className: "row-meta" }, 
    `${form.student_name || 'Student'} (${form.student_matric || 'No matric'}) · Offence: ${form.offence_type || 'other'} · Venue: ${form.venue || 'Venue'}`
  ), React.createElement("p", {
    style: { fontSize: 12, lineHeight: 1.7, whiteSpace: 'pre-wrap' }
  }, form.description || 'No narrative entered.'))), React.createElement("div", {
    className: "field",
    style: { marginTop: 16 }
  }, React.createElement("label", null, "Evidence file (optional — JPG, PNG, WEBP, PDF, DOCX)"), React.createElement("input", {
    type: "file",
    name: "evidence",
    accept: ".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt"
  })))), React.createElement("div", {
    className: "modal-head",
    style: { justifyContent: 'flex-end', gap: 9 }
  }, step > 1 && React.createElement("button", {
    type: "button",
    className: "ghost-button",
    onClick: () => setStep(step - 1)
  }, "Back"), step < 3 ? React.createElement("button", {
    type: "button",
    className: "primary-button",
    onClick: () => setStep(step + 1)
  }, "Continue ", React.createElement("i", { className: "fas fa-arrow-right" })) : React.createElement("button", {
    className: "primary-button",
    type: "submit"
  }, React.createElement("i", { className: "fas fa-paper-plane" }), "Submit report")))))),
  showRadarModal && radarData && React.createElement(Modal, {
    title: `Disciplinary Profile: ${radarData.student?.full_name || form.student_name || 'Student'}`,
    subtitle: `Matric: ${radarData.matric_no} · Risk Status: ${radarData.risk_badge}`,
    close: () => setShowRadarModal(false),
    width: "780px"
  }, React.createElement("div", null,
    React.createElement("div", { className: "form-grid", style: { marginBottom: 16 } },
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Faculty & Department"), React.createElement("strong", { className: "row-title" }, `${radarData.student?.faculty_name || 'N/A'} · ${radarData.student?.department_name || 'N/A'}`)),
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Total Past Infractions"), React.createElement("strong", { className: "row-title", style: { color: '#be123c' } }, `${radarData.total_incidents} Infraction(s)`)),
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Active Sanctions"), React.createElement("strong", { className: "row-title" }, `${radarData.active_sanctions} Sanction(s)`)),
      React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Risk Classification"), React.createElement("span", { className: `status-pill ${radarData.total_incidents > 1 ? 'urgent' : 'pending'}` }, radarData.risk_badge))
    ),
    React.createElement("div", { className: "panel-title", style: { marginBottom: 10, fontSize: 13 } }, "Prior Recorded Exam Infractions"),
    React.createElement("div", { style: { overflowX: 'auto' } },
      React.createElement("table", { className: "modern-table" },
        React.createElement("thead", null, React.createElement("tr", null,
          React.createElement("th", null, "Ref / Case"),
          React.createElement("th", null, "Date"),
          React.createElement("th", null, "Course"),
          React.createElement("th", null, "Offense"),
          React.createElement("th", null, "Sanction"),
          React.createElement("th", null, "Result Status")
        )),
        React.createElement("tbody", null, radarData.incidents?.map(it => React.createElement("tr", { key: it.id },
          React.createElement("td", null, React.createElement("strong", null, it.case_no || it.reference_no)),
          React.createElement("td", null, it.incident_date || '—'),
          React.createElement("td", null, it.course_code || '—'),
          React.createElement("td", null, (it.offense_category || '').replace('_', ' ')),
          React.createElement("td", null, it.sanction_type ? React.createElement("span", { className: "status-pill urgent" }, it.sanction_type) : 'None / Pending'),
          React.createElement("td", null, it.result_status ? React.createElement("span", { className: `result-badge ${it.result_status}` }, it.result_status) : '—')
        )))
      )
    )
  ));
}

function Cases({ api, openCase, user }) {
  const [rows, setRows] = useState([]),
    [q, setQ] = useState('');
  useEffect(() => {
    api('get_cases').then(r => setRows(r.data || []));
  }, []);
  const filtered = rows.filter(r => JSON.stringify(r).toLowerCase().includes(q.toLowerCase()));
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Workflow",
    title: "Case management",
    sub: "Track investigations, hearings, decisions and closure."
  }), React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "search-box"
  }, React.createElement("i", { className: "fas fa-search" }), React.createElement("input", {
    value: q,
    onChange: e => setQ(e.target.value),
    placeholder: "Search case number or student..."
  })), React.createElement("div", {
    className: "row-meta"
  }, filtered.length, " cases")), React.createElement("div", {
    style: { overflowX: 'auto' }
  }, React.createElement("table", {
    className: "modern-table"
  }, React.createElement("thead", null, React.createElement("tr", null, 
    React.createElement("th", null, "Case"), 
    React.createElement("th", null, "Student"), 
    React.createElement("th", null, "Offence"), 
    React.createElement("th", null, "Stage"), 
    React.createElement("th", null, "Priority"), 
    React.createElement("th", null, "Committee"),
    React.createElement("th", null, "Updated"), 
    React.createElement("th", null, "Action")
  )), React.createElement("tbody", null, filtered.map(r => React.createElement("tr", {
    key: r.id
  }, React.createElement("td", null, 
    React.createElement("strong", null, r.case_no), 
    React.createElement("div", { className: "row-meta" }, r.reference_no)
  ), React.createElement("td", null, r.student_name, React.createElement("div", {
    className: "row-meta"
  }, r.student_matric)), React.createElement("td", null, (r.offence_type || '').replace('_', ' ')), 
  React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${r.stage}`
  }, r.stage.replace('_', ' '))), React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${r.priority}`
  }, r.priority)), 
  React.createElement("td", null, r.committee_member_name || React.createElement("span", { className: "row-meta" }, "Unassigned")),
  React.createElement("td", null, new Date(r.updated_at).toLocaleDateString()), 
  React.createElement("td", null, React.createElement("button", {
    className: "primary-button",
    onClick: () => openCase(r.id)
  }, React.createElement("i", { className: "fas fa-folder-open" }), " Open")))))), !filtered.length && React.createElement("div", {
    className: "empty-state"
  }, "No cases are available in your workspace."))));
}

function CaseWorkspace({ data, user, api, notify, onBack, refresh, lookups }) {
  const [comment, setComment] = useState(''),
    [grounds, setGrounds] = useState(''),
    [showAppeal, setShowAppeal] = useState(false),
    [showResolve, setShowResolve] = useState(false),
    [showHearingModal, setShowHearingModal] = useState(false),
    [showEscalate, setShowEscalate] = useState(false),
    [showResultStatus, setShowResultStatus] = useState(false),
    [showLetterModal, setShowLetterModal] = useState(false),
    [letterType, setLetterType] = useState('query_letter'),
    [letterData, setLetterData] = useState(null),
    [loadingLetter, setLoadingLetter] = useState(false),
    [showLodgeModal, setShowLodgeModal] = useState(false),
    [showCustodyLedger, setShowCustodyLedger] = useState(false),
    [activeLockerLogs, setActiveLockerLogs] = useState([]),
    [escalateForm, setEscalateForm] = useState({ case_tier: 'faculty', transfer_notes: '' }),
    [resultForm, setResultForm] = useState({ result_status: 'normal', lockout_start_date: '', lockout_end_date: '' }),
    [lodgeForm, setLodgeForm] = useState({ locker_identifier: 'Safe-A1', shelf_drawer: 'Shelf 1, Bin 1', description: 'Malpractice confiscated items', barcode_tag: '' }),
    [resolveForm, setResolveForm] = useState({
      status: 'resolved',
      sanction_type: 'warning',
      duration: '',
      description: '',
      effective_date: new Date().toISOString().split('T')[0],
      resolution_summary: ''
    }),
    [hearingForm, setHearingForm] = useState({
      date: '',
      venue: ''
    }),
    [stageNote, setStageNote] = useState('');

  if (!data) return React.createElement("div", {
    className: "empty-state"
  }, "Loading case dossier...");

  const c = data.case;
  const isOfficerOrAdmin = ['admin', 'exam_officer'].includes(user.role);
  const isAssignedCommittee = user.role === 'committee' && c.assigned_committee == user.id;
  const canProgressStage = isOfficerOrAdmin || isAssignedCommittee;

  const addComment = async () => {
    if (!comment.trim()) return;
    await api('add_case_comment', {
      case_id: c.id,
      body: comment,
      visibility: 'internal'
    });
    setComment('');
    notify('Comment added to case record');
    refresh();
  };

  const advanceStage = async targetStage => {
    try {
      await api('advance_case_stage', {
        case_id: c.id,
        stage: targetStage,
        note: stageNote || `Stage advanced to ${targetStage.replace('_', ' ')}`
      });
      setStageNote('');
      notify(`Case moved to ${targetStage.replace('_', ' ')}`);
      refresh();
    } catch (e) {
      notify(e.response?.data?.message || 'Unable to transition stage');
    }
  };

  const submitResolve = async e => {
    e.preventDefault();
    try {
      await api('resolve_case', {
        case_id: c.id,
        ...resolveForm
      });
      setShowResolve(false);
      notify('Case decision officially finalized and recorded');
      refresh();
    } catch (e) {
      notify(e.response?.data?.message || 'Failed to resolve case');
    }
  };

  const scheduleHearing = async e => {
    e.preventDefault();
    try {
      await api('schedule_hearing', {
        case_id: c.id,
        date: hearingForm.date,
        venue: hearingForm.venue
      });
      setShowHearingModal(false);
      setHearingForm({ date: '', venue: '' });
      notify('Hearing officially scheduled');
      refresh();
    } catch (e) {
      notify(e.response?.data?.message || 'Unable to schedule hearing');
    }
  };

  const handleEscalate = async e => {
    e.preventDefault();
    try {
      await api('escalate_case_tier', {
        case_id: c.id,
        ...escalateForm
      });
      setShowEscalate(false);
      notify('Panel jurisdiction updated');
      refresh();
    } catch (e) {
      notify(e.response?.data?.message || 'Error escalating case');
    }
  };

  const handleResultStatus = async e => {
    e.preventDefault();
    try {
      await api('update_result_status', {
        case_id: c.id,
        ...resultForm
      });
      setShowResultStatus(false);
      notify('Examination result status and lockout updated');
      refresh();
    } catch (e) {
      notify(e.response?.data?.message || 'Error updating result status');
    }
  };

  const fetchLetter = async type => {
    setLetterType(type);
    setLoadingLetter(true);
    try {
      const res = await api('get_official_letter', { case_id: c.id, type });
      setLetterData(res.data);
    } catch (e) {
      notify('Unable to compile official letter');
    } finally {
      setLoadingLetter(false);
    }
  };

  const handleLodgeEvidence = async e => {
    e.preventDefault();
    try {
      await api('tag_evidence_locker', {
        incident_id: c.incident_id,
        ...lodgeForm
      });
      setShowLodgeModal(false);
      notify('Physical evidence lodged into safe');
      refresh();
    } catch (e) {
      notify(e.response?.data?.message || 'Failed to lodge evidence');
    }
  };

  const viewCustodyLedger = async lockerId => {
    try {
      const res = await api('get_evidence_transfers', { evidence_locker_id: lockerId });
      setActiveLockerLogs(res.data || []);
      setShowCustodyLedger(true);
    } catch (e) {
      notify('Unable to fetch custody ledger');
    }
  };

  const appeal = async () => {
    if (!grounds.trim()) return;
    await api('submit_appeal', {
      case_id: c.id,
      grounds
    });
    setGrounds('');
    setShowAppeal(false);
    notify('Appeal submitted for review');
    refresh();
  };

  return React.createElement(React.Fragment, null, 
    // Top Bar & Action Buttons
    React.createElement(SectionHead, {
      eyebrow: "Case dossier",
      title: `${c.case_no} — ${c.student_name}`,
      sub: `Reference: ${c.reference_no} · ${c.student_matric} · ${c.course_code || ''}`,
      action: React.createElement("div", { style: { display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' } },
        React.createElement("span", {
          className: `tier-badge ${c.case_tier || 'central'}`,
          title: "Disciplinary Jurisdictional Tier"
        }, React.createElement("i", { className: "fas fa-building-columns" }), ` Tier: ${c.case_tier || 'central'}`),
        React.createElement("span", {
          className: `result-badge ${c.result_status || 'normal'}`,
          title: "Examination Result Status"
        }, `Result: ${c.result_status || 'normal'}`),
        canProgressStage && React.createElement("button", {
          className: "ghost-button no-print",
          onClick: () => { setEscalateForm({ case_tier: c.case_tier || 'faculty', transfer_notes: '' }); setShowEscalate(true); }
        }, React.createElement("i", { className: "fas fa-arrows-split-up-and-left" }), " Escalate Tier"),
        isOfficerOrAdmin && React.createElement("button", {
          className: "ghost-button no-print",
          onClick: () => { setResultForm({ result_status: c.result_status || 'normal', lockout_start_date: c.lockout_start_date || '', lockout_end_date: c.lockout_end_date || '' }); setShowResultStatus(true); }
        }, React.createElement("i", { className: "fas fa-lock" }), " Result Hold"),
        React.createElement("button", {
          className: "gold-button no-print",
          onClick: () => { setShowLetterModal(true); fetchLetter('query_letter'); }
        }, React.createElement("i", { className: "fas fa-file-contract" }), " Official Letters"),
        React.createElement("button", {
          className: "ghost-button no-print",
          onClick: onBack
        }, React.createElement("i", { className: "fas fa-arrow-left" }), " Back"),
        React.createElement("button", {
          className: "primary-button no-print",
          onClick: () => window.print()
        }, React.createElement("i", { className: "fas fa-print" }), " Print dossier")
      )
    }),

    // Official Print Header for Physical Document Output
    React.createElement("div", { className: "print-only print-header" },
      React.createElement("img", { src: "assets/buk-logo.png", alt: "BUK Crest" }),
      React.createElement("h2", { style: { margin: '8px 0 2px', fontSize: 18 } }, "BAYERO UNIVERSITY KANO"),
      React.createElement("div", { style: { fontSize: 12, fontWeight: 800, textTransform: 'uppercase' } }, "Central Examination Malpractice Committee (CEMC)"),
      React.createElement("div", { style: { fontSize: 11, color: '#555' } }, `Official Case Investigation Dossier · ${c.case_no}`)
    ),

    React.createElement("div", {
      className: "grid-2"
    }, 
    // Left Column: Core Dossier, Timeline, Evidence
    React.createElement("div", null, 
      React.createElement("div", {
        className: "panel"
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", null, 
        React.createElement("div", { className: "panel-title" }, "Case timeline"), 
        React.createElement("div", { className: "panel-sub" }, "Chronological audit of investigation and panel decisions")
      ), React.createElement("span", {
        className: `status-pill ${c.stage}`
      }, c.stage.replace('_', ' '))), React.createElement("div", {
        className: "panel-body"
      }, React.createElement("div", {
        className: "timeline"
      }, data.logs?.map(l => React.createElement("div", {
        className: "timeline-item",
        key: 'l' + l.id
      }, React.createElement("span", {
        className: "timeline-dot"
      }), React.createElement("strong", null, (l.action || '').replace('_', ' '), " · ", l.actor_name), 
      React.createElement("p", null, l.note || 'Workflow event recorded.', React.createElement("br", null), 
      React.createElement("small", null, new Date(l.logged_at).toLocaleString())))), 
      data.comments?.map(l => React.createElement("div", {
        className: "timeline-item",
        key: 'c' + l.id
      }, React.createElement("span", {
        className: "timeline-dot",
        style: { background: 'var(--buk-blue)' }
      }), React.createElement("strong", null, "Internal Note · ", l.author_name), 
      React.createElement("p", null, l.body, React.createElement("br", null), 
      React.createElement("small", null, new Date(l.created_at).toLocaleString()))))))),

      // Confiscated Physical / Digital Evidence Gallery
      React.createElement("div", {
        className: "panel",
        style: { marginTop: 18 }
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", null,
        React.createElement("div", { className: "panel-title" }, "Confiscated evidence records"),
        React.createElement("div", { className: "panel-sub" }, "Verified chain of custody and forensic attachments")
      ), React.createElement("span", { className: "row-meta" }, `${data.evidence?.length || 0} item(s)`)),
      React.createElement("div", { className: "panel-body" },
        data.evidence?.length ? data.evidence.map(ev => React.createElement("div", {
          className: "evidence-card",
          key: ev.id
        }, React.createElement("div", null,
          React.createElement("strong", null, React.createElement("i", { className: "fas fa-shield-halved", style: { color: 'var(--buk-blue)', marginRight: 8 } }), ev.original_name),
          React.createElement("small", null, `${(ev.file_size / 1024).toFixed(1)} KB · Uploaded ${new Date(ev.uploaded_at).toLocaleDateString()}`),
          ev.file_hash && React.createElement("div", { style: { fontSize: 9, fontFamily: 'monospace', color: 'var(--muted)', marginTop: 2 } }, `SHA-256: ${ev.file_hash.slice(0, 24)}…`)
        ), React.createElement("button", {
          className: "primary-button no-print",
          style: { minHeight: 34, fontSize: 11, padding: '0 12px' },
          onClick: () => window.open(`api.php?action=download_evidence&id=${ev.id}`, '_blank')
        }, React.createElement("i", { className: "fas fa-download" }), " Download file"))) : React.createElement("div", { className: "empty-state" }, "No physical or electronic evidence attached.")
      )),

      // Confiscated Physical Evidence Safe Locker & Barcode Chain-of-Custody
      React.createElement("div", {
        className: "panel",
        style: { marginTop: 18 }
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", null,
        React.createElement("div", { className: "panel-title" }, "Physical evidence safe & barcode lockers"),
        React.createElement("div", { className: "panel-sub" }, "Physical safe inventory, barcode custody tracking")
      ), canProgressStage && React.createElement("button", {
        className: "ghost-button no-print",
        onClick: () => setShowLodgeModal(true)
      }, React.createElement("i", { className: "fas fa-box-archive" }), " Lodge in safe")),
      React.createElement("div", { className: "panel-body" },
        data.lockers?.length ? data.lockers.map(lk => React.createElement("div", {
          className: "locker-card",
          key: 'lk-' + lk.id,
          style: { marginBottom: 10 }
        }, React.createElement("div", { className: "locker-tag-header" },
          React.createElement("div", null,
            React.createElement("span", { className: "barcode-tag" }, React.createElement("i", { className: "fas fa-barcode", style: { marginRight: 5 } }), lk.barcode_tag),
            React.createElement("div", { className: "locker-loc", style: { marginTop: 5 } }, React.createElement("i", { className: "fas fa-vault" }), `${lk.locker_identifier} (${lk.shelf_drawer || 'Drawer 1'})`)
          ),
          React.createElement("span", { className: `status-pill ${lk.status === 'secured' ? 'completed' : 'pending'}` }, lk.status)
        ),
        React.createElement("div", { style: { fontSize: 12, color: 'var(--ink)', margin: '6px 0' } }, lk.description),
        React.createElement("div", { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 10, borderTop: '1px solid #edf2f6', paddingTop: 8 } },
          React.createElement("small", { className: "row-meta" }, `Current Custodian: ${lk.custodian_name || 'Staff'}`),
          React.createElement("button", {
            className: "ghost-button no-print",
            style: { minHeight: 28, fontSize: 10 },
            onClick: () => viewCustodyLedger(lk.id)
          }, React.createElement("i", { className: "fas fa-timeline" }), " Custody Ledger")
        )
      )) : React.createElement("div", { className: "empty-state" },
        React.createElement("i", { className: "fas fa-box-open" }),
        React.createElement("p", null, "No physical evidence currently lodged in university safe lockers for this incident.")
      ))),

      // Add Case Note
      React.createElement("div", {
        className: "panel no-print",
        style: { marginTop: 18 }
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", {
        className: "panel-title"
      }, "Add confidential note")), React.createElement("div", {
        className: "panel-body"
      }, React.createElement("div", {
        className: "field"
      }, React.createElement("textarea", {
        value: comment,
        onChange: e => setComment(e.target.value),
        placeholder: "Record investigation notes, witness testimonies, or panel observations..."
      })), React.createElement("button", {
        className: "primary-button",
        style: { marginTop: 10 },
        onClick: addComment
      }, React.createElement("i", { className: "fas fa-comment" }), " Post internal note")))),

    // Right Column: Summary, Stage Advancement, Hearings, Sanctions
    React.createElement("div", null,
      // Summary Card
      React.createElement("div", {
        className: "panel"
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", {
        className: "panel-title"
      }, "Student & offense summary")), React.createElement("div", {
        className: "panel-body"
      }, React.createElement("div", { className: "form-grid" },
        React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Student Name"), React.createElement("strong", { className: "row-title" }, c.student_name)),
        React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Matric Number"), React.createElement("strong", { className: "row-title" }, c.student_matric)),
        React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Course"), React.createElement("strong", { className: "row-title" }, c.course_code)),
        React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Offence Type"), React.createElement("strong", { className: "row-title", style: { textTransform: 'capitalize' } }, (c.offence_type || '').replace('_', ' ')))
      ), React.createElement("div", { style: { marginTop: 14 } },
        React.createElement("small", { className: "row-meta" }, "Incident Narrative"),
        React.createElement("p", { style: { fontSize: 12, lineHeight: 1.6, background: '#ffffff', border: '1px solid var(--line)', padding: 12, borderRadius: 10, marginTop: 4, whiteSpace: 'pre-wrap' } }, c.description)
      ))),

      // Workflow Stage Progression Controls (for authorized roles)
      canProgressStage && React.createElement("div", {
        className: "panel no-print",
        style: { marginTop: 18 }
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", null,
        React.createElement("div", { className: "panel-title" }, "Stage progression"),
        React.createElement("div", { className: "panel-sub" }, `Currently: ${c.stage.replace('_', ' ')}`)
      )), React.createElement("div", {
        className: "panel-body"
      }, React.createElement("div", { className: "field" },
        React.createElement("label", null, "Transition Note"),
        React.createElement("input", {
          value: stageNote,
          onChange: e => setStageNote(e.target.value),
          placeholder: "Reason or panel decision note for stage change..."
        })
      ), React.createElement("div", { style: { display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 12 } },
        c.stage === 'reported' && React.createElement("button", {
          className: "primary-button",
          onClick: () => advanceStage('under_review')
        }, React.createElement("i", { className: "fas fa-play" }), " Begin Review"),
        c.stage === 'under_review' && React.createElement("button", {
          className: "primary-button",
          onClick: () => advanceStage('investigation')
        }, React.createElement("i", { className: "fas fa-magnifying-glass" }), " Move to Investigation"),
        c.stage === 'investigation' && React.createElement("button", {
          className: "primary-button",
          onClick: () => setShowHearingModal(true)
        }, React.createElement("i", { className: "fas fa-calendar-check" }), " Schedule Hearing"),
        !['resolved', 'dismissed'].includes(c.stage) && React.createElement("button", {
          className: "gold-button",
          onClick: () => setShowResolve(true)
        }, React.createElement("i", { className: "fas fa-gavel" }), " Record Sanction / Resolve")
      ))),

      // Formal Sanctions & Ruling Details (if resolved)
      data.sanction && React.createElement("div", {
        className: "panel",
        style: { marginTop: 18, border: '1px solid var(--buk-gold)' }
      }, React.createElement("div", {
        className: "panel-head",
        style: { background: '#ffffff' }
      }, React.createElement("div", {
        className: "panel-title",
        style: { color: '#8c5905' }
      }, React.createElement("i", { className: "fas fa-scale-balanced", style: { marginRight: 8 } }), "Official Disciplinary Verdict")),
      React.createElement("div", { className: "panel-body" },
        React.createElement("div", { className: "form-grid" },
          React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Sanction Type"), React.createElement("strong", { className: "row-title", style: { textTransform: 'capitalize' } }, (data.sanction.sanction_type || '').replace('_', ' '))),
          React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Duration"), React.createElement("strong", { className: "row-title" }, data.sanction.duration || 'Indefinite / Permanent')),
          React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Effective Date"), React.createElement("strong", { className: "row-title" }, data.sanction.effective_date)),
          React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Recorded By"), React.createElement("strong", { className: "row-title" }, data.sanction.recorded_by_name || 'Committee'))
        ),
        React.createElement("div", { style: { marginTop: 12 } },
          React.createElement("small", { className: "row-meta" }, "Sanction Description & Ruling Details"),
          React.createElement("p", { style: { fontSize: 12, lineHeight: 1.6, marginTop: 4 } }, data.sanction.description)
        )
      )),

      // Hearings Section
      React.createElement("div", {
        className: "panel",
        style: { marginTop: 18 }
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", {
        className: "panel-title"
      }, "Scheduled hearings"),
      canProgressStage && React.createElement("button", {
        className: "ghost-button no-print",
        onClick: () => setShowHearingModal(true)
      }, React.createElement("i", { className: "fas fa-calendar-plus" }), " Schedule")),
      React.createElement("div", {
        className: "panel-body"
      }, data.hearings?.length ? data.hearings.map(h => React.createElement("div", {
        className: "list-row",
        key: h.id
      }, React.createElement("div", null, 
        React.createElement("div", { className: "row-title" }, new Date(h.scheduled_date).toLocaleString()), 
        React.createElement("div", { className: "row-meta" }, h.venue)
      ), React.createElement("span", {
        className: `status-pill ${h.status}`
      }, h.status))) : React.createElement("div", {
        className: "empty-state"
      }, "No hearings scheduled for this case."))),

      // Appeals Section
      React.createElement("div", {
        className: "panel",
        style: { marginTop: 18 }
      }, React.createElement("div", {
        className: "panel-head"
      }, React.createElement("div", {
        className: "panel-title"
      }, "Appeals registry"),
      React.createElement("button", {
        className: "gold-button no-print",
        onClick: () => setShowAppeal(true)
      }, React.createElement("i", { className: "fas fa-scale-balanced" }), " Lodge appeal")),
      React.createElement("div", {
        className: "panel-body"
      }, data.appeals?.length ? data.appeals.map(a => React.createElement("div", {
        className: "list-row",
        key: a.id
      }, React.createElement("div", null, 
        React.createElement("div", { className: "row-title" }, `Appeal: ${a.status}`), 
        React.createElement("div", { className: "row-meta", style: { marginTop: 3 } }, a.grounds),
        a.decision && React.createElement("div", { style: { fontSize: 11, color: 'var(--buk-blue)', marginTop: 4 } }, `Decision: ${a.decision}`)
      ), React.createElement("span", {
        className: `status-pill ${a.status}`
      }, a.status))) : React.createElement("div", {
        className: "empty-state"
      }, "No appeals submitted for this case.")))
    )),

    // Print Signature Block
    React.createElement("div", { className: "print-only official-stamp" },
      React.createElement("div", null,
        React.createElement("p", null, "___________________________________"),
        React.createElement("strong", null, "Chairman, Malpractice Panel"),
        React.createElement("p", null, "Bayero University Kano")
      ),
      React.createElement("div", null,
        React.createElement("p", null, "___________________________________"),
        React.createElement("strong", null, "Registrar / Academic Secretary"),
        React.createElement("p", null, "Bayero University Kano")
      )
    ),

    // Resolution & Sanctions Modal
    showResolve && React.createElement(Modal, {
      title: "Record Verdict & Finalize Case",
      subtitle: `${c.case_no} — ${c.student_name}`,
      close: () => setShowResolve(false),
      width: "680px"
    }, React.createElement("form", { onSubmit: submitResolve },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Final Case Status" },
          React.createElement("select", {
            value: resolveForm.status,
            onChange: e => setResolveForm({ ...resolveForm, status: e.target.value })
          }, React.createElement("option", { value: "resolved" }, "Resolved (Impose Sanction)"),
             React.createElement("option", { value: "dismissed" }, "Dismissed (Exonerate Student)")
          )
        ),
        resolveForm.status === 'resolved' && React.createElement(Field, { label: "Sanction Imposed" },
          React.createElement("select", {
            value: resolveForm.sanction_type,
            onChange: e => setResolveForm({ ...resolveForm, sanction_type: e.target.value })
          }, React.createElement("option", { value: "warning" }, "Official Written Warning"),
             React.createElement("option", { value: "course_cancellation" }, "Course Cancellation / Carryover"),
             React.createElement("option", { value: "suspension_semester" }, "Suspension (One Semester)"),
             React.createElement("option", { value: "suspension_year" }, "Suspension (One Academic Year)"),
             React.createElement("option", { value: "expulsion" }, "Expulsion from University"),
             React.createElement("option", { value: "other" }, "Other Sanction")
          )
        ),
        resolveForm.status === 'resolved' && React.createElement(Field, { label: "Duration" },
          React.createElement("input", {
            value: resolveForm.duration,
            onChange: e => setResolveForm({ ...resolveForm, duration: e.target.value }),
            placeholder: "e.g. 1 Academic Year, 2 Semesters"
          })
        ),
        resolveForm.status === 'resolved' && React.createElement(Field, { label: "Effective Date" },
          React.createElement("input", {
            type: "date",
            required: true,
            value: resolveForm.effective_date,
            onChange: e => setResolveForm({ ...resolveForm, effective_date: e.target.value })
          })
        ),
        React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
          React.createElement("label", null, "Panel Resolution Summary"),
          React.createElement("textarea", {
            required: true,
            value: resolveForm.resolution_summary,
            onChange: e => setResolveForm({ ...resolveForm, resolution_summary: e.target.value }),
            placeholder: "Summarize findings of the committee, hearing evidence, and Senate-approved ruling."
          })
        ),
        resolveForm.status === 'resolved' && React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
          React.createElement("label", null, "Sanction Order Text"),
          React.createElement("textarea", {
            required: true,
            value: resolveForm.description,
            onChange: e => setResolveForm({ ...resolveForm, description: e.target.value }),
            placeholder: "Official text of the sanction to be placed on student's permanent academic record."
          })
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowResolve(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" }, React.createElement("i", { className: "fas fa-check" }), " Confirm & Record Verdict")
      )
    )),

    // Schedule Hearing Modal
    showHearingModal && React.createElement(Modal, {
      title: "Schedule Disciplinary Hearing",
      subtitle: `${c.case_no} — ${c.student_name}`,
      close: () => setShowHearingModal(false),
      width: "560px"
    }, React.createElement("form", { onSubmit: scheduleHearing },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Date and Time", style: { gridColumn: '1/-1' } },
          React.createElement("input", {
            type: "datetime-local",
            required: true,
            value: hearingForm.date,
            onChange: e => setHearingForm({ ...hearingForm, date: e.target.value })
          })
        ),
        React.createElement(Field, { label: "Venue", style: { gridColumn: '1/-1' } },
          React.createElement("select", {
            required: true,
            value: hearingForm.venue,
            onChange: e => setHearingForm({ ...hearingForm, venue: e.target.value })
          }, React.createElement("option", { value: "" }, "Select Hearing Venue"),
             lookups.venues?.map(v => React.createElement("option", { key: v.id, value: v.name }, `${v.name} (${v.location || 'BUK'})`))
          )
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowHearingModal(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" }, React.createElement("i", { className: "fas fa-calendar-check" }), " Schedule Hearing")
      )
    )),

    // Appeal Modal
    showAppeal && React.createElement(Modal, {
      title: "Submit Case Appeal",
      subtitle: `${c.case_no} — ${c.student_name}`,
      close: () => setShowAppeal(false),
      width: "600px"
    }, React.createElement("div", null,
      React.createElement("div", { className: "field" },
        React.createElement("label", null, "Grounds for Appeal"),
        React.createElement("textarea", {
          value: grounds,
          onChange: e => setGrounds(e.target.value),
          placeholder: "State specific grounds, procedural errors, or mitigating evidence justifying review."
        })
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { className: "ghost-button", onClick: () => setShowAppeal(false) }, "Cancel"),
        React.createElement("button", { className: "primary-button", onClick: appeal }, "Submit appeal")
      )
    )),

    // Panel Jurisdiction Escalation Modal
    showEscalate && React.createElement(Modal, {
      title: "Escalate Case Panel Jurisdiction",
      subtitle: `${c.case_no} — ${c.student_name}`,
      close: () => setShowEscalate(false),
      width: "560px"
    }, React.createElement("form", { onSubmit: handleEscalate },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Target Jurisdictional Tier", style: { gridColumn: '1/-1' } },
          React.createElement("select", {
            value: escalateForm.case_tier,
            onChange: e => setEscalateForm({ ...escalateForm, case_tier: e.target.value })
          },
            React.createElement("option", { value: "departmental" }, "Departmental Panel (HOD Jurisdiction)"),
            React.createElement("option", { value: "faculty" }, "Faculty Examination Misconduct Committee (FEMC)"),
            React.createElement("option", { value: "central" }, "Central Examination Misconduct Committee (CEMC)")
          )
        ),
        React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
          React.createElement("label", null, "Transfer & Escalation Rationale"),
          React.createElement("textarea", {
            required: true,
            value: escalateForm.transfer_notes,
            onChange: e => setEscalateForm({ ...escalateForm, transfer_notes: e.target.value }),
            placeholder: "Specify why this case is transferred between jurisdictional tiers (e.g. cross-faculty course, severe penalty recommendation)..."
          })
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowEscalate(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" }, React.createElement("i", { className: "fas fa-check" }), " Transfer Jurisdiction")
      )
    )),

    // Result Status & Lockout Modal
    showResultStatus && React.createElement(Modal, {
      title: "Examination Result Hold & Lockout Control",
      subtitle: `${c.case_no} — ${c.student_name} (${c.student_matric})`,
      close: () => setShowResultStatus(false),
      width: "560px"
    }, React.createElement("form", { onSubmit: handleResultStatus },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Student Result Disposition", style: { gridColumn: '1/-1' } },
          React.createElement("select", {
            value: resultForm.result_status,
            onChange: e => setResultForm({ ...resultForm, result_status: e.target.value })
          },
            React.createElement("option", { value: "normal" }, "Normal (Result Accessible)"),
            React.createElement("option", { value: "held" }, "Result Held (Under Malpractice Investigation)"),
            React.createElement("option", { value: "nullified" }, "Result Nullified (Sanction Enforced)"),
            React.createElement("option", { value: "released" }, "Result Released (Exonerated / Sanction Completed)")
          )
        ),
        React.createElement(Field, { label: "Lockout Start Date" },
          React.createElement("input", {
            type: "date",
            value: resultForm.lockout_start_date || '',
            onChange: e => setResultForm({ ...resultForm, lockout_start_date: e.target.value })
          })
        ),
        React.createElement(Field, { label: "Lockout End Date" },
          React.createElement("input", {
            type: "date",
            value: resultForm.lockout_end_date || '',
            onChange: e => setResultForm({ ...resultForm, lockout_end_date: e.target.value })
          })
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowResultStatus(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" }, React.createElement("i", { className: "fas fa-lock" }), " Update Result Status")
      )
    )),

    // Official BUK Letterhead Stationery Modal
    showLetterModal && React.createElement(Modal, {
      title: "Official BUK Letterhead Stationery & Legal Documents",
      subtitle: `${c.case_no} · ${c.student_name} (${c.student_matric})`,
      close: () => setShowLetterModal(false),
      width: "920px"
    }, React.createElement("div", null,
      React.createElement("div", { className: "segmented no-print", style: { marginBottom: 20 } },
        React.createElement("button", {
          className: letterType === 'query_letter' ? 'active' : '',
          onClick: () => fetchLetter('query_letter')
        }, React.createElement("i", { className: "fas fa-file-lines", style: { marginRight: 6 } }), "Formal Query"),
        React.createElement("button", {
          className: letterType === 'summons_subpoena' ? 'active' : '',
          onClick: () => fetchLetter('summons_subpoena')
        }, React.createElement("i", { className: "fas fa-bullhorn", style: { marginRight: 6 } }), "Hearing Summons / Subpoena"),
        React.createElement("button", {
          className: letterType === 'senate_order' ? 'active' : '',
          onClick: () => fetchLetter('senate_order')
        }, React.createElement("i", { className: "fas fa-stamp", style: { marginRight: 6 } }), "Senate Sanction Order")
      ),
      loadingLetter ? React.createElement("div", { className: "empty-state" },
        React.createElement("i", { className: "fas fa-circle-notch fa-spin fa-2x" }),
        React.createElement("p", null, "Generating print-ready official stationery document...")
      ) : letterData && React.createElement("div", { className: "letterhead-sheet" },
        React.createElement("div", { className: "letterhead-top" },
          React.createElement("img", { src: "assets/buk-logo.png", alt: "BUK Crest", className: "letterhead-logo" }),
          React.createElement("div", { className: "letterhead-title-block" },
            React.createElement("h1", null, "BAYERO UNIVERSITY, KANO"),
            React.createElement("h2", null, "CENTRAL EXAMINATION MISCONDUCT COMMITTEE (CEMC)"),
            React.createElement("p", null, "P.M.B. 3011, BUK Road, Kano State, Nigeria · registrar@buk.edu.ng · www.buk.edu.ng")
          )
        ),
        React.createElement("div", { className: "letterhead-meta-row" },
          React.createElement("div", null, React.createElement("strong", null, "REF: "), letterData.ref_no),
          React.createElement("div", null, React.createElement("strong", null, "DATE: "), letterData.date)
        ),
        React.createElement("div", { className: "letterhead-recipient" },
          React.createElement("strong", null, letterData.recipient?.name), React.createElement("br", null),
          React.createElement("span", null, `Matriculation No: ${letterData.recipient?.matric}`), React.createElement("br", null),
          React.createElement("span", null, `Department: ${letterData.recipient?.dept || 'General Studies'}`), React.createElement("br", null),
          React.createElement("span", null, `Faculty: ${letterData.recipient?.faculty || 'Bayero University Kano'}`)
        ),
        React.createElement("div", { className: "letterhead-subject" }, letterData.subject),
        letterData.body?.map((p, idx) => React.createElement("p", { className: "letterhead-body-p", key: 'p-' + idx }, p)),
        React.createElement("div", { className: "letterhead-sign" },
          React.createElement("div", { className: "sign-box" },
            React.createElement("div", { style: { height: 40 } }),
            React.createElement("div", { className: "sign-line" },
              letterData.signatory?.name,
              React.createElement("br", null),
              React.createElement("small", { style: { fontWeight: 'normal' } }, letterData.signatory?.title)
            )
          ),
          React.createElement("div", { className: "official-seal-circle" }, "OFFICIAL BUK SEAL / RATIFIED CEMC")
        )
      ),
      React.createElement("div", { className: "modal-actions no-print" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowLetterModal(false) }, "Close"),
        React.createElement("button", { type: "button", className: "primary-button", onClick: () => window.print() },
          React.createElement("i", { className: "fas fa-print" }), " Print Document"
        )
      )
    )),

    // Lodge Physical Evidence Modal
    showLodgeModal && React.createElement(Modal, {
      title: "Lodge Evidence in Safe Locker",
      subtitle: `${c.case_no} — ${c.student_name}`,
      close: () => setShowLodgeModal(false),
      width: "560px"
    }, React.createElement("form", { onSubmit: handleLodgeEvidence },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Physical Locker Safe", style: { gridColumn: '1/-1' } },
          React.createElement("input", {
            required: true,
            value: lodgeForm.locker_identifier,
            onChange: e => setLodgeForm({ ...lodgeForm, locker_identifier: e.target.value }),
            placeholder: "e.g. Safe-A1 (CEMC Central Repository)"
          })
        ),
        React.createElement(Field, { label: "Shelf / Drawer / Bin" },
          React.createElement("input", {
            required: true,
            value: lodgeForm.shelf_drawer,
            onChange: e => setLodgeForm({ ...lodgeForm, shelf_drawer: e.target.value }),
            placeholder: "e.g. Shelf 2, Drawer B"
          })
        ),
        React.createElement(Field, { label: "Barcode Tag (leave blank to auto-generate)" },
          React.createElement("input", {
            value: lodgeForm.barcode_tag,
            onChange: e => setLodgeForm({ ...lodgeForm, barcode_tag: e.target.value }),
            placeholder: "e.g. BUK-EV-2026-XXXX"
          })
        ),
        React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
          React.createElement("label", null, "Description of Confiscated Evidence"),
          React.createElement("textarea", {
            required: true,
            value: lodgeForm.description,
            onChange: e => setLodgeForm({ ...lodgeForm, description: e.target.value }),
            placeholder: "Detailed inventory of items (e.g. scribbled booklet, calculator cover, physical micro-notes)..."
          })
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowLodgeModal(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" }, React.createElement("i", { className: "fas fa-shield-halved" }), " Lodge & Secure Evidence")
      )
    )),

    // Chain of Custody Timeline Modal
    showCustodyLedger && React.createElement(Modal, {
      title: "Chain of Custody Transfer Ledger",
      subtitle: "Audited Physical Transfer History",
      close: () => setShowCustodyLedger(false),
      width: "650px"
    }, React.createElement("div", null,
      activeLockerLogs.length ? React.createElement("div", { className: "custody-timeline" },
        activeLockerLogs.map((log, idx) => React.createElement("div", { className: "custody-node", key: 'cl-' + log.id },
          React.createElement("strong", { style: { fontSize: 13 } }, `${log.from_custodian_name || 'Intake'} ➔ ${log.to_custodian_name}`),
          React.createElement("div", { style: { fontSize: 11, color: 'var(--muted)', marginTop: 2 } },
            `${new Date(log.transferred_at).toLocaleString()} · Purpose: ${(log.reason || '').replace('_', ' ')}`
          ),
          log.notes && React.createElement("p", { style: { fontSize: 12, margin: '5px 0 0', background: '#ffffff', border: '1px solid var(--line)', padding: 8, borderRadius: 8 } }, log.notes)
        ))
      ) : React.createElement("div", { className: "empty-state" }, "No custody transfers logged for this item yet.")
    ))
  );
}

function Hearings({ data, lookups, api, notify, openCase }) {
  const [form, setForm] = useState({});
  const submit = async e => {
    e.preventDefault();
    try {
      await api('schedule_hearing', form);
      notify('Hearing scheduled successfully');
      setForm({});
    } catch (e) {
      notify(e.response?.data?.message || 'Error scheduling hearing');
    }
  };
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Workflow",
    title: "Hearing management",
    sub: "Schedule and track formal disciplinary hearings."
  }), React.createElement("div", {
    className: "grid-2"
  }, React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Upcoming hearings")), React.createElement("div", {
    className: "panel-body"
  }, data.hearings?.length ? data.hearings.map(h => React.createElement("div", {
    className: "list-row",
    key: h.id
  }, React.createElement("div", null, 
    React.createElement("div", { className: "row-title" }, h.case_no), 
    React.createElement("div", { className: "row-meta" }, `${h.student_name} · ${h.venue}`, React.createElement("br", null), new Date(h.scheduled_date).toLocaleString())
  ), React.createElement("div", { style: { display: 'flex', gap: 6, alignItems: 'center' } },
    React.createElement("span", { className: `status-pill ${h.status}` }, h.status),
    h.case_id && React.createElement("button", {
      className: "ghost-button",
      style: { padding: '4px 8px', fontSize: 11 },
      onClick: () => openCase(h.case_id)
    }, "Open Case")
  ))) : React.createElement("div", {
    className: "empty-state"
  }, "No upcoming hearings."))), React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Schedule a hearing")), React.createElement("form", {
    className: "panel-body",
    onSubmit: submit
  }, React.createElement("div", {
    className: "form-grid"
  }, React.createElement(Field, {
    label: "Case ID"
  }, React.createElement("input", {
    required: true,
    value: form.case_id || '',
    onChange: e => setForm({ ...form, case_id: e.target.value }),
    placeholder: "Enter internal case ID"
  })), React.createElement(Field, {
    label: "Date and time"
  }, React.createElement("input", {
    required: true,
    type: "datetime-local",
    value: form.date || '',
    onChange: e => setForm({ ...form, date: e.target.value })
  })), React.createElement(Field, {
    label: "Venue",
    style: { gridColumn: '1/-1' }
  }, React.createElement("select", {
    required: true,
    value: form.venue || '',
    onChange: e => setForm({ ...form, venue: e.target.value })
  }, React.createElement("option", { value: "" }, "Choose venue"), 
     lookups.venues?.map(v => React.createElement("option", { key: v.id, value: v.name }, v.name))
  ))), React.createElement("button", {
    className: "primary-button",
    style: { marginTop: 17 }
  }, React.createElement("i", { className: "fas fa-calendar-plus" }), "Schedule hearing")))));
}

function Calendar({ api }) {
  const [events, setEvents] = useState([]);
  useEffect(() => {
    api('get_calendar').then(r => setEvents(r.data || []));
  }, []);
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Planning",
    title: "Calendar",
    sub: "Hearings, tasks and academic sessions in one view."
  }), React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Upcoming events")), React.createElement("div", {
    className: "panel-body"
  }, events.length ? events.sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at)).map(e => React.createElement("div", {
    className: "list-row",
    key: e.event_type + e.id
  }, React.createElement("div", null, 
    React.createElement("div", { className: "row-title" }, e.title), 
    React.createElement("div", { className: "row-meta" }, e.location || 'Institutional calendar', " · ", new Date(e.starts_at).toLocaleString())
  ), React.createElement("span", {
    className: `status-pill ${e.status}`
  }, e.event_type))) : React.createElement("div", {
    className: "empty-state"
  }, "No events scheduled."))));
}

function Reports({ api }) {
  const [data, setData] = useState(null);
  useEffect(() => {
    api('get_analytics').then(r => setData(r.data));
  }, []);
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Insights",
    title: "Reports & analytics",
    sub: "Understand workload, case stages and operational trends."
  }), React.createElement("div", {
    className: "grid-2"
  }, React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Cases by stage")), React.createElement("div", {
    className: "panel-body"
  }, data?.stages && Object.entries(data.stages).map(([k, v]) => React.createElement("div", {
    className: "list-row",
    key: k
  }, React.createElement("div", { className: "row-title" }, k), React.createElement("strong", null, v))), 
  !data && React.createElement("div", { className: "empty-state" }, "Loading analytics..."))), 
  React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Monthly incident trend")), React.createElement("div", {
    className: "panel-body"
  }, data?.monthly && Object.entries(data.monthly).map(([k, v]) => React.createElement("div", {
    className: "list-row",
    key: k
  }, React.createElement("div", { className: "row-title" }, k), React.createElement("div", {
    style: {
      width: 140,
      height: 8,
      background: '#ffffff',
      border: '1px solid var(--line)',
      borderRadius: 99
    }
  }, React.createElement("div", {
    style: {
      height: '100%',
      width: `${Math.min(100, v / Math.max(...Object.values(data.monthly), 1) * 100)}%`,
      background: 'var(--buk-gold)',
      borderRadius: 99
    }
  })), React.createElement("strong", null, v)))))));
}

function Drafts({ api, notify, onContinueDraft }) {
  const [rows, setRows] = useState([]), 
    [title, setTitle] = useState(''), 
    [saving, setSaving] = useState(false);
  
  const load = () => api('get_drafts').then(r => setRows(r.data || []));
  useEffect(() => { load(); }, []);

  const create = async () => { 
    setSaving(true); 
    await api('save_draft', { title: title || 'Untitled incident draft', payload: { step: 1 }, progress: 10 }); 
    setTitle(''); 
    await load(); 
    setSaving(false); 
    notify('Draft saved'); 
  };

  const remove = async id => { 
    await api('delete_draft', { draft_id: id }); 
    await load(); 
    notify('Draft deleted'); 
  };

  return React.createElement(React.Fragment, null,
    React.createElement(SectionHead, {
      eyebrow: 'Productivity', 
      title: 'My drafts', 
      sub: 'Continue incomplete incident reports without losing your work.', 
      action: React.createElement('button', { className: 'primary-button', onClick: create, disabled: saving }, 
        React.createElement('i', { className: 'fas fa-plus' }), saving ? 'Saving...' : 'New draft'
      )
    }),
    React.createElement('div', { className: 'panel', style: { marginBottom: 18 } },
      React.createElement('div', { className: 'panel-body' },
        React.createElement('div', { className: 'form-grid' },
          React.createElement(Field, { label: 'Draft title', style: { gridColumn: '1/-1' } },
            React.createElement('input', { value: title, onChange: e => setTitle(e.target.value), placeholder: 'e.g. CSC 301 incident report — Hall A' })
          ),
          React.createElement('button', { className: 'primary-button', style: { marginTop: 4 }, onClick: create },
            React.createElement('i', { className: 'fas fa-save' }), ' Save draft'
          )
        )
      )
    ),
    React.createElement('div', { className: 'panel' },
      React.createElement('div', { className: 'panel-body' },
        rows.length ? rows.map(d => React.createElement('div', { className: 'list-row', key: d.id },
          React.createElement('div', null,
            React.createElement('div', { className: 'row-title' }, d.title),
            React.createElement('div', { className: 'row-meta' }, 'Updated ', new Date(d.updated_at).toLocaleString())
          ),
          React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 8 } },
            React.createElement('span', { className: 'status-pill in_progress' }, d.progress, '% complete'),
            React.createElement('button', {
              className: 'primary-button',
              onClick: () => {
                if (onContinueDraft) onContinueDraft(d.payload || {});
              }
            }, React.createElement('i', { className: 'fas fa-pen-to-square' }), ' Continue'),
            React.createElement('button', { className: 'ghost-button', onClick: () => remove(d.id) },
              React.createElement('i', { className: 'fas fa-trash' })
            )
          )
        )) : React.createElement('div', { className: 'empty-state' },
          React.createElement('i', { className: 'fas fa-file-pen' }),
          React.createElement('div', null, 'No saved drafts yet.')
        )
      )
    )
  );
}

function Announcements({ api, notify }) {
  const [title, setTitle] = useState(''),
    [body, setBody] = useState('');
  const publish = async e => {
    e.preventDefault();
    await api('create_announcement', {
      title,
      body,
      audience_role: ''
    });
    setTitle('');
    setBody('');
    notify('Announcement published successfully');
  };
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Administration",
    title: "Announcements",
    sub: "Publish clear updates to the BUK EIMS workspace."
  }), React.createElement("div", {
    className: "panel"
  }, React.createElement("form", {
    className: "panel-body",
    onSubmit: publish
  }, React.createElement("div", {
    className: "form-grid"
  }, React.createElement(Field, {
    label: "Announcement title",
    style: { gridColumn: '1/-1' }
  }, React.createElement("input", {
    required: true,
    value: title,
    onChange: e => setTitle(e.target.value),
    placeholder: "e.g. Malpractice Committee sitting schedule"
  })), React.createElement(Field, {
    label: "Message",
    style: { gridColumn: '1/-1' }
  }, React.createElement("textarea", {
    required: true,
    value: body,
    onChange: e => setBody(e.target.value),
    placeholder: "Write clear institutional instructions..."
  }))), React.createElement("button", {
    className: "primary-button",
    style: { marginTop: 16 }
  }, React.createElement("i", { className: "fas fa-bullhorn" }), "Publish announcement"))));
}

function UserManagement({ api, user, lookups, notify }) {
  const h = React.createElement;
  const [tab, setTab] = useState('requests');
  const [users, setUsers] = useState([]);
  const [requests, setRequests] = useState([]);
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(true);
  const blank = { staff_id: '', full_name: '', email: '', password: '', role: 'invigilator', faculty_id: '', department_id: '' };
  const [form, setForm] = useState(blank);

  const load = async () => {
    setLoading(true);
    try {
      const [u, r] = await Promise.all([api('get_users'), api('get_account_requests', { status: 'all' })]);
      setUsers(u.data || []);
      setRequests(r.data || []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const field = (label, key, type = 'text') => h('div', { className: 'field' },
    h('label', null, label),
    h('input', { type, value: form[key] || '', onChange: e => setForm({ ...form, [key]: e.target.value }), placeholder: label })
  );

  const select = (label, key, options) => h('div', { className: 'field' },
    h('label', null, label),
    h('select', { value: form[key] || '', onChange: e => setForm({ ...form, [key]: e.target.value }) },
      options.map(o => h('option', { key: o.value, value: o.value }, o.label))
    )
  );

  const create = async () => {
    try {
      await api('create_user', form);
      notify('User created successfully');
      setShow(false);
      setForm(blank);
      load();
    } catch (e) {
      notify(e.response?.data?.message || 'Error creating user');
    }
  };

  const approve = async id => {
    try {
      await api('approve_account_request', { id });
      notify('Account approved and created');
      load();
    } catch (e) {
      notify(e.response?.data?.message || 'Error approving request');
    }
  };

  const reject = async id => {
    try {
      await api('reject_account_request', { id });
      notify('Account request declined');
      load();
    } catch (e) {
      notify(e.response?.data?.message || 'Error declining request');
    }
  };

  const roleOptions = [
    { value: 'invigilator', label: 'Invigilator' },
    { value: 'exam_officer', label: 'Examination Officer' },
    { value: 'hod', label: 'HOD / Dean' },
    { value: 'committee', label: 'Committee Member' },
    { value: 'admin', label: 'Administrator' }
  ];

  return h(React.Fragment, null,
    h(SectionHead, {
      eyebrow: 'Administration',
      title: 'User management',
      sub: 'Create staff accounts and review new access requests from the modern workspace.',
      action: h('button', { className: 'primary-button', onClick: () => { setForm(blank); setShow(true); } },
        h('i', { className: 'fas fa-user-plus' }), ' Add user'
      )
    }),
    h('div', { className: 'grid-3', style: { marginBottom: 18 } },
      h('div', { className: 'stat-card' },
        h('div', { className: 'stat-icon blue' }, h('i', { className: 'fas fa-users' })),
        h('div', null, h('strong', null, users.length), h('span', null, 'Active directory'))
      ),
      h('div', { className: 'stat-card' },
        h('div', { className: 'stat-icon gold' }, h('i', { className: 'fas fa-user-clock' })),
        h('div', null, h('strong', null, requests.filter(x => x.status === 'pending').length), h('span', null, 'Pending requests'))
      ),
      h('div', { className: 'stat-card' },
        h('div', { className: 'stat-icon green' }, h('i', { className: 'fas fa-user-check' })),
        h('div', null, h('strong', null, requests.filter(x => x.status === 'approved').length), h('span', null, 'Approved requests'))
      )
    ),
    h('div', { className: 'panel' },
      h('div', { className: 'panel-head' },
        h('div', null,
          h('div', { className: 'panel-title' }, 'Staff access centre'),
          h('div', { className: 'panel-sub' }, 'Review requests and manage the BUK EIMS directory.')
        ),
        h('div', { className: 'segmented' },
          h('button', { className: tab === 'requests' ? 'active' : '', onClick: () => setTab('requests') }, 'Account requests'),
          h('button', { className: tab === 'directory' ? 'active' : '', onClick: () => setTab('directory') }, 'User directory')
        )
      ),
      h('div', { className: 'panel-body' },
        loading ? h('div', { className: 'empty-state' }, h('i', { className: 'fas fa-circle-notch fa-spin' }), ' Loading user records...') :
        tab === 'requests' ? (
          requests.length ? h('div', { className: 'stack-list' },
            requests.map(r => h('div', { className: 'list-row', key: r.id },
              h('div', null,
                h('div', { className: 'row-title' }, r.full_name),
                h('div', { className: 'row-meta' }, `${r.email} · ${r.staff_id} · ${(r.role_requested || '').replace('_', ' ')}`),
                h('div', { className: 'row-meta' }, `${r.department_name || 'No department selected'} · ${new Date(r.created_at).toLocaleString()}`)
              ),
              h('div', { style: { display: 'flex', gap: 7, alignItems: 'center' } },
                h('span', { className: `status-pill ${r.status}` }, r.status),
                r.status === 'pending' && h(React.Fragment, null,
                  h('button', { className: 'primary-button', onClick: () => approve(r.id) }, h('i', { className: 'fas fa-check' }), ' Approve'),
                  h('button', { className: 'ghost-button', onClick: () => reject(r.id) }, h('i', { className: 'fas fa-xmark' }), ' Decline')
                )
              )
            ))
          ) : h('div', { className: 'empty-state' }, h('i', { className: 'fas fa-user-clock' }), ' No account requests yet.')
        ) : (
          h('div', { className: 'table-wrap' },
            h('table', null,
              h('thead', null, h('tr', null, h('th', null, 'Staff'), h('th', null, 'Email'), h('th', null, 'Role'), h('th', null, 'Status'))),
              h('tbody', null, users.map(u => h('tr', { key: u.id },
                h('td', null, h('strong', null, u.full_name), h('small', null, u.staff_id)),
                h('td', null, u.email),
                h('td', null, (u.role || '').replace('_', ' ')),
                h('td', null, h('span', { className: `status-pill ${u.is_active ? 'active' : 'disabled'}` }, u.is_active ? 'Active' : 'Disabled'))
              )))
            )
          )
        )
      )
    ),
    show && h(Modal, {
      title: 'Create staff account',
      subtitle: 'Add a trusted member directly to the BUK EIMS directory.',
      close: () => setShow(false),
      width: '640px'
    }, h('div', null,
      field('Staff ID', 'staff_id'),
      field('Full name', 'full_name'),
      field('BUK email', 'email', 'email'),
      select('System role', 'role', roleOptions),
      field('Initial password', 'password', 'password'),
      h('div', { className: 'modal-actions' },
        h('button', { className: 'ghost-button', onClick: () => setShow(false) }, 'Cancel'),
        h('button', { className: 'primary-button', onClick: create }, h('i', { className: 'fas fa-user-plus' }), ' Create account')
      )
    ))
  );
}

function Settings({ api, user, notify }) {
  const [pref, setPref] = useState({
    default_view: 'overview',
    notify_in_app: true,
    notify_email: true,
    compact_mode: false
  });
  useEffect(() => {
    api('get_preferences').then(r => setPref(r.data || pref));
  }, []);
  const save = async () => {
    await api('save_preferences', pref);
    notify('Preferences saved successfully');
  };
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Account",
    title: "Profile & preferences",
    sub: "Keep your workspace personal, clear and focused."
  }), React.createElement("div", {
    className: "grid-2"
  }, React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Profile")), React.createElement("div", {
    className: "panel-body"
  }, React.createElement("div", {
    className: "avatar",
    style: {
      width: 60,
      height: 60,
      fontSize: 20,
      marginBottom: 15
    }
  }, user.full_name?.split(' ').map(x => x[0]).join('').slice(0, 2)), React.createElement("div", {
    className: "row-title"
  }, user.full_name), React.createElement("div", {
    className: "row-meta"
  }, user.role.replace('_', ' '), " · BUK EIMS"))), React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Workspace preferences")), React.createElement("div", {
    className: "panel-body"
  }, React.createElement("label", {
    style: {
      display: 'flex',
      gap: 10,
      alignItems: 'center',
      fontSize: 12,
      fontWeight: 700,
      marginBottom: 16
    }
  }, React.createElement("input", {
    type: "checkbox",
    checked: !!pref.notify_in_app,
    onChange: e => setPref({ ...pref, notify_in_app: e.target.checked })
  }), " In-app notifications"), React.createElement("label", {
    style: {
      display: 'flex',
      gap: 10,
      alignItems: 'center',
      fontSize: 12,
      fontWeight: 700,
      marginBottom: 16
    }
  }, React.createElement("input", {
    type: "checkbox",
    checked: !!pref.notify_email,
    onChange: e => setPref({ ...pref, notify_email: e.target.checked })
  }), " Email notifications"), React.createElement("button", {
    className: "primary-button",
    onClick: save
  }, React.createElement("i", { className: "fas fa-check" }), "Save preferences")))));
}

// --- Phase 2: Panic Alert Emergency Modal ---
function PanicAlertModal({ close, api, notify, lookups, onDispatched }) {
  const [form, setForm] = useState({
    venue: lookups.venues?.[0]?.name || '',
    session_time: 'Morning (8:30am - 11:30am)',
    urgency: 'critical',
    reason: ''
  });
  const [submitting, setSubmitting] = useState(false);

  const presets = [
    'Threat or Physical Confrontation against Invigilator',
    'Organized Mass Impersonation / Unauthorized Syndicate',
    'Hall Disruption, Shouting, or Mob Disobedience',
    'Violent Resistance to Device / Material Confiscation'
  ];

  const submit = async e => {
    e.preventDefault();
    if (!form.venue.trim() || !form.reason.trim()) {
      notify('Please provide venue and description of emergency');
      return;
    }
    setSubmitting(true);
    try {
      await api('trigger_security_alert', form);
      notify('🚨 Emergency Hall Alert Dispatched to Chief Invigilator & University Security Command');
      onDispatched();
    } catch (err) {
      notify(err.response?.data?.message || 'Error dispatching security alert');
      setSubmitting(false);
    }
  };

  return React.createElement(Modal, {
    title: "🚨 Emergency Hall Security Alert",
    subtitle: "Immediate Dispatch to Chief Invigilator & Campus Security Command",
    close,
    width: "600px"
  }, React.createElement("form", { onSubmit: submit },
    React.createElement("div", { className: "form-grid" },
      React.createElement(Field, { label: "Examination Hall / Venue", style: { gridColumn: '1/-1' } },
        React.createElement("input", {
          required: true,
          value: form.venue,
          onChange: e => setForm({ ...form, venue: e.target.value }),
          placeholder: "e.g. Twin Lecture Theatre A, New Campus"
        }),
        React.createElement("div", { style: { display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 6 } },
          lookups.venues?.slice(0, 5).map(v => React.createElement("button", {
            type: "button",
            key: v.id,
            className: "ghost-button",
            style: { minHeight: 26, fontSize: 10, padding: '0 8px' },
            onClick: () => setForm({ ...form, venue: v.name })
          }, v.name))
        )
      ),
      React.createElement(Field, { label: "Examination Session" },
        React.createElement("select", {
          value: form.session_time,
          onChange: e => setForm({ ...form, session_time: e.target.value })
        },
          React.createElement("option", { value: "Morning (8:30am - 11:30am)" }, "Morning (8:30am - 11:30am)"),
          React.createElement("option", { value: "Afternoon (12:00pm - 3:00pm)" }, "Afternoon (12:00pm - 3:00pm)"),
          React.createElement("option", { value: "Evening (3:30pm - 6:30pm)" }, "Evening (3:30pm - 6:30pm)")
        )
      ),
      React.createElement(Field, { label: "Urgency Level" },
        React.createElement("select", {
          value: form.urgency,
          onChange: e => setForm({ ...form, urgency: e.target.value })
        },
          React.createElement("option", { value: "critical" }, "CRITICAL — Armed / Physical Security Required"),
          React.createElement("option", { value: "high" }, "HIGH — Chief Invigilator Intervention Required")
        )
      ),
      React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
        React.createElement("label", null, "Emergency Description & Nature of Disturbance"),
        React.createElement("textarea", {
          required: true,
          value: form.reason,
          onChange: e => setForm({ ...form, reason: e.target.value }),
          placeholder: "Describe the incident or select a preset below..."
        }),
        React.createElement("div", { style: { display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 8 } },
          presets.map((p, idx) => React.createElement("button", {
            type: "button",
            key: 'pre-' + idx,
            className: "ghost-button",
            style: { minHeight: 26, fontSize: 10, padding: '0 8px', background: '#fff1f2', borderColor: '#fecdd3', color: '#9f1239' },
            onClick: () => setForm({ ...form, reason: p })
          }, p))
        )
      )
    ),
    React.createElement("div", { className: "modal-actions" },
      React.createElement("button", { type: "button", className: "ghost-button", onClick: close }, "Cancel"),
      React.createElement("button", { type: "submit", className: "panic-btn ghost-button", disabled: submitting },
        React.createElement("i", { className: "fas fa-triangle-exclamation" }),
        submitting ? " Dispatching Alert..." : " Broadcast Emergency Alarm"
      )
    )
  ));
}

// --- Phase 2: Senate Memos & Ratification Engine ---
function SenateMemos({ api, notify, user, lookups, openCase }) {
  const [memos, setMemos] = useState([]);
  const [sessionFilter, setSessionFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [showCompile, setShowCompile] = useState(false);
  const [compileForm, setCompileForm] = useState({
    academic_session: lookups.sessions?.[0]?.name || '2025/2026',
    meeting_number: '',
    title: 'Consideration of Examination Misconduct Cases',
    case_ids: []
  });
  const [eligibleCases, setEligibleCases] = useState([]);
  const [activeMemo, setActiveMemo] = useState(null);
  const [memoCases, setMemoCases] = useState([]);
  const [loadingDetails, setLoadingDetails] = useState(false);
  const [showRatifyModal, setShowRatifyModal] = useState(false);
  const [ratifyForm, setRatifyForm] = useState({
    status: 'senate_approved',
    meeting_number: '',
    ratification_notes: ''
  });

  const loadMemos = () => {
    let q = '';
    if (sessionFilter) q += `&session=${encodeURIComponent(sessionFilter)}`;
    if (statusFilter) q += `&status=${encodeURIComponent(statusFilter)}`;
    api(`get_senate_memos${q ? q : ''}`, {}, 'get').then(r => setMemos(r.data || [])).catch(() => {});
  };

  useEffect(() => {
    loadMemos();
  }, [sessionFilter, statusFilter]);

  const loadEligibleCases = async () => {
    try {
      const res = await api('get_cases');
      const eligible = (res.data || []).filter(c => ['resolved', 'dismissed'].includes(c.stage) && !c.senate_memo_id);
      setEligibleCases(eligible);
      setCompileForm(prev => ({ ...prev, case_ids: eligible.map(x => x.id) }));
    } catch (e) {
      notify('Unable to load eligible cases for memo');
    }
  };

  const handleOpenCompile = () => {
    loadEligibleCases();
    setShowCompile(true);
  };

  const submitCompile = async e => {
    e.preventDefault();
    if (!compileForm.case_ids.length) {
      notify('Please select at least one resolved case to compile');
      return;
    }
    try {
      await api('create_senate_memo', compileForm);
      notify('Senate Working Paper created successfully');
      setShowCompile(false);
      loadMemos();
    } catch (e) {
      notify(e.response?.data?.message || 'Error compiling memo');
    }
  };

  const openMemoDetails = async memoId => {
    setLoadingDetails(true);
    try {
      const res = await api('get_senate_memo_details', { memo_id: memoId }, 'get');
      setActiveMemo(res.data.memo);
      setMemoCases(res.data.cases || []);
      setRatifyForm({
        status: res.data.memo.status === 'draft' ? 'recommended_to_senate' : res.data.memo.status,
        meeting_number: res.data.memo.meeting_number || '',
        ratification_notes: res.data.memo.ratification_notes || ''
      });
    } catch (e) {
      notify('Unable to load Senate memo details');
    } finally {
      setLoadingDetails(false);
    }
  };

  const submitRatification = async e => {
    e.preventDefault();
    try {
      await api('update_senate_ratification', {
        memo_id: activeMemo.id,
        ...ratifyForm
      });
      notify('Senate ratification status updated');
      setShowRatifyModal(false);
      openMemoDetails(activeMemo.id);
      loadMemos();
    } catch (e) {
      notify(e.response?.data?.message || 'Error updating ratification');
    }
  };

  const toggleCaseSelect = cid => {
    const exists = compileForm.case_ids.includes(cid);
    const updated = exists ? compileForm.case_ids.filter(x => x !== cid) : [...compileForm.case_ids, cid];
    setCompileForm({ ...compileForm, case_ids: updated });
  };

  const isOfficerOrAdmin = ['admin', 'exam_officer'].includes(user.role);

  return React.createElement(React.Fragment, null,
    React.createElement(SectionHead, {
      eyebrow: "Governance & Approvals",
      title: "Senate Memos & Ratification",
      sub: "Compile CEMC working papers, track Senate approvals, and generate official gazette extracts.",
      action: isOfficerOrAdmin && React.createElement("button", {
        className: "primary-button",
        onClick: handleOpenCompile
      }, React.createElement("i", { className: "fas fa-file-circle-plus" }), " Compile Working Paper")
    }),

    // Stat Cards
    React.createElement("div", { className: "stat-grid" },
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon blue" }, React.createElement("i", { className: "fas fa-landmark" })),
        React.createElement("div", null,
          React.createElement("strong", null, memos.length),
          React.createElement("span", null, "Senate Working Papers")
        )
      ),
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon green" }, React.createElement("i", { className: "fas fa-stamp" })),
        React.createElement("div", null,
          React.createElement("strong", null, memos.filter(m => m.status === 'senate_approved').length),
          React.createElement("span", null, "Senate Approved & Ratified")
        )
      ),
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon gold" }, React.createElement("i", { className: "fas fa-clock" })),
        React.createElement("div", null,
          React.createElement("strong", null, memos.filter(m => ['draft', 'recommended_to_senate'].includes(m.status)).length),
          React.createElement("span", null, "Pending Ratification")
        )
      ),
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon blue" }, React.createElement("i", { className: "fas fa-folder-closed" })),
        React.createElement("div", null,
          React.createElement("strong", null, memos.reduce((acc, m) => acc + (parseInt(m.case_count) || 0), 0)),
          React.createElement("span", null, "Total Cases Processed")
        )
      )
    ),

    // Panel with Table
    React.createElement("div", { className: "panel" },
      React.createElement("div", { className: "panel-head" },
        React.createElement("div", { style: { display: 'flex', gap: 10, flexWrap: 'wrap' } },
          React.createElement("select", {
            value: sessionFilter,
            onChange: e => setSessionFilter(e.target.value),
            style: { border: '1px solid var(--line)', borderRadius: 10, padding: '0 12px', height: 38, fontSize: 12 }
          },
            React.createElement("option", { value: "" }, "All Academic Sessions"),
            lookups.sessions?.map(s => React.createElement("option", { key: s.id, value: s.name }, s.name))
          ),
          React.createElement("select", {
            value: statusFilter,
            onChange: e => setStatusFilter(e.target.value),
            style: { border: '1px solid var(--line)', borderRadius: 10, padding: '0 12px', height: 38, fontSize: 12 }
          },
            React.createElement("option", { value: "" }, "All Ratification Statuses"),
            React.createElement("option", { value: "draft" }, "Draft"),
            React.createElement("option", { value: "recommended_to_senate" }, "Recommended to Senate"),
            React.createElement("option", { value: "senate_approved" }, "Senate Approved"),
            React.createElement("option", { value: "senate_amended" }, "Senate Amended"),
            React.createElement("option", { value: "senate_rejected" }, "Senate Rejected")
          )
        ),
        React.createElement("span", { className: "row-meta" }, `${memos.length} memo(s) found`)
      ),
      React.createElement("div", { style: { overflowX: 'auto' } },
        React.createElement("table", { className: "modern-table" },
          React.createElement("thead", null, React.createElement("tr", null,
            React.createElement("th", null, "Memo Ref"),
            React.createElement("th", null, "Session & Meeting"),
            React.createElement("th", null, "Title"),
            React.createElement("th", null, "Cases"),
            React.createElement("th", null, "Ratification Status"),
            React.createElement("th", null, "Compiled By"),
            React.createElement("th", null, "Action")
          )),
          React.createElement("tbody", null, memos.map(m => React.createElement("tr", { key: m.id },
            React.createElement("td", null, React.createElement("strong", null, m.memo_number)),
            React.createElement("td", null, m.academic_session, m.meeting_number && React.createElement("div", { className: "row-meta" }, `Meeting: ${m.meeting_number}`)),
            React.createElement("td", null, m.title),
            React.createElement("td", null, React.createElement("span", { className: "status-pill open" }, `${m.case_count || 0} cases`)),
            React.createElement("td", null, React.createElement("span", {
              className: `status-pill ${m.status === 'senate_approved' ? 'completed' : (m.status === 'senate_rejected' ? 'urgent' : 'pending')}`
            }, (m.status || '').replace(/_/g, ' '))),
            React.createElement("td", null, m.created_by_name || 'Staff'),
            React.createElement("td", null, React.createElement("button", {
              className: "primary-button",
              style: { minHeight: 32, fontSize: 11 },
              onClick: () => openMemoDetails(m.id)
            }, React.createElement("i", { className: "fas fa-eye" }), " View Dossier"))
          ))),
          !memos.length && React.createElement("tr", null, React.createElement("td", { colSpan: 7, className: "empty-state" }, "No Senate working papers compiled yet."))
        )
      )
    ),

    // Compile Modal
    showCompile && React.createElement(Modal, {
      title: "Compile Senate Working Paper",
      subtitle: "Aggregate resolved malpractice cases for official Senate presentation",
      close: () => setShowCompile(false),
      width: "820px"
    }, React.createElement("form", { onSubmit: submitCompile },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Academic Session" },
          React.createElement("select", {
            value: compileForm.academic_session,
            onChange: e => setCompileForm({ ...compileForm, academic_session: e.target.value })
          }, lookups.sessions?.map(s => React.createElement("option", { key: s.id, value: s.name }, s.name)))
        ),
        React.createElement(Field, { label: "Senate Regular Meeting Number" },
          React.createElement("input", {
            value: compileForm.meeting_number,
            onChange: e => setCompileForm({ ...compileForm, meeting_number: e.target.value }),
            placeholder: "e.g. 168th Regular Meeting"
          })
        ),
        React.createElement(Field, { label: "Working Paper Title", style: { gridColumn: '1/-1' } },
          React.createElement("input", {
            required: true,
            value: compileForm.title,
            onChange: e => setCompileForm({ ...compileForm, title: e.target.value }),
            placeholder: "e.g. Report of the Central Examination Misconduct Committee on First Semester Malpractice"
          })
        )
      ),
      React.createElement("div", { style: { marginTop: 16 } },
        React.createElement("div", { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 } },
          React.createElement("strong", { style: { fontSize: 12, textTransform: 'uppercase', color: 'var(--muted)' } },
            `Select Resolved Cases (${compileForm.case_ids.length} of ${eligibleCases.length} selected)`
          ),
          React.createElement("button", {
            type: "button",
            className: "ghost-button",
            style: { minHeight: 26, fontSize: 10 },
            onClick: () => setCompileForm({ ...compileForm, case_ids: compileForm.case_ids.length === eligibleCases.length ? [] : eligibleCases.map(x => x.id) })
          }, compileForm.case_ids.length === eligibleCases.length ? "Deselect All" : "Select All")
        ),
        React.createElement("div", { style: { maxHeight: 300, overflowY: 'auto', border: '1px solid var(--line)', borderRadius: 10 } },
          eligibleCases.length ? eligibleCases.map(ec => React.createElement("label", {
            key: ec.id,
            style: {
              display: 'flex',
              alignItems: 'center',
              gap: 12,
              padding: 10,
              borderBottom: '1px solid #edf2f6',
              cursor: 'pointer',
              background: compileForm.case_ids.includes(ec.id) ? '#f0f9ff' : '#fff'
            }
          },
            React.createElement("input", {
              type: "checkbox",
              checked: compileForm.case_ids.includes(ec.id),
              onChange: () => toggleCaseSelect(ec.id)
            }),
            React.createElement("div", { style: { flex: 1 } },
              React.createElement("strong", { style: { fontSize: 12 } }, `${ec.case_no} — ${ec.student_name}`),
              React.createElement("div", { className: "row-meta" }, `${ec.student_matric} · ${ec.course_code || ''} · ${(ec.offence_type || '').replace('_', ' ')}`)
            ),
            React.createElement("span", { className: `status-pill ${ec.stage}` }, ec.stage)
          )) : React.createElement("div", { className: "empty-state" }, "No uncompiled resolved cases found.")
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowCompile(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" },
          React.createElement("i", { className: "fas fa-file-export" }), " Compile Working Paper"
        )
      )
    )),

    // Memo Details & Official Gazette Modal
    activeMemo && React.createElement(Modal, {
      title: activeMemo.title,
      subtitle: `${activeMemo.memo_number} · Academic Session: ${activeMemo.academic_session} ${activeMemo.meeting_number ? `(${activeMemo.meeting_number})` : ''}`,
      close: () => setActiveMemo(null),
      width: "950px"
    }, React.createElement("div", null,
      React.createElement("div", { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid var(--line)', paddingBottom: 14, marginBottom: 16 } },
        React.createElement("div", { style: { display: 'flex', gap: 8, alignItems: 'center' } },
          React.createElement("span", {
            className: `status-pill ${activeMemo.status === 'senate_approved' ? 'completed' : (activeMemo.status === 'senate_rejected' ? 'urgent' : 'pending')}`
          }, `Status: ${(activeMemo.status || '').replace(/_/g, ' ')}`),
          activeMemo.ratification_date && React.createElement("span", { className: "row-meta" }, `Ratified on: ${activeMemo.ratification_date}`)
        ),
        React.createElement("div", { style: { display: 'flex', gap: 8 } },
          isOfficerOrAdmin && React.createElement("button", {
            className: "gold-button no-print",
            onClick: () => setShowRatifyModal(true)
          }, React.createElement("i", { className: "fas fa-stamp" }), " Ratification Controls"),
          React.createElement("button", {
            className: "primary-button no-print",
            onClick: () => window.print()
          }, React.createElement("i", { className: "fas fa-print" }), " Print Gazette Extract")
        )
      ),

      // Summary Statistics Callout
      activeMemo.summary_statistics && React.createElement("div", { className: "form-grid", style: { marginBottom: 16 } },
        React.createElement("div", { className: "panel", style: { background: '#ffffff', border: '1px solid var(--line)', padding: 14, boxShadow: 'none' } },
          React.createElement("div", { className: "panel-title", style: { fontSize: 11, textTransform: 'uppercase', color: 'var(--muted)', marginBottom: 8 } }, "Sanctions Breakdown"),
          React.createElement("div", { style: { display: 'flex', gap: 6, flexWrap: 'wrap' } },
            Object.entries(activeMemo.summary_statistics.by_sanction || {}).map(([sanc, qty]) => React.createElement("span", {
              className: "status-pill urgent",
              key: sanc
            }, `${sanc.replace('_', ' ')}: ${qty}`))
          )
        ),
        React.createElement("div", { className: "panel", style: { background: '#ffffff', border: '1px solid var(--line)', padding: 14, boxShadow: 'none' } },
          React.createElement("div", { className: "panel-title", style: { fontSize: 11, textTransform: 'uppercase', color: 'var(--muted)', marginBottom: 8 } }, "Faculty Distribution"),
          React.createElement("div", { style: { display: 'flex', gap: 6, flexWrap: 'wrap' } },
            Object.entries(activeMemo.summary_statistics.by_faculty || {}).map(([fac, qty]) => React.createElement("span", {
              className: "status-pill open",
              key: fac
            }, `${fac}: ${qty}`))
          )
        )
      ),

      // Cases Table in Gazette
      React.createElement("div", { className: "panel-title", style: { fontSize: 13, marginBottom: 10 } }, `Compiled Malpractice Cases (${memoCases.length})`),
      React.createElement("div", { style: { overflowX: 'auto' } },
        React.createElement("table", { className: "modern-table" },
          React.createElement("thead", null, React.createElement("tr", null,
            React.createElement("th", null, "Case / Ref"),
            React.createElement("th", null, "Student Details"),
            React.createElement("th", null, "Faculty & Dept"),
            React.createElement("th", null, "Course"),
            React.createElement("th", null, "Recommended Sanction"),
            React.createElement("th", null, "Result Disposition"),
            React.createElement("th", { className: "no-print" }, "Action")
          )),
          React.createElement("tbody", null, memoCases.map(mc => React.createElement("tr", { key: mc.id },
            React.createElement("td", null, React.createElement("strong", null, mc.case_no), React.createElement("div", { className: "row-meta" }, mc.reference_no)),
            React.createElement("td", null, mc.student_name, React.createElement("div", { className: "row-meta" }, mc.student_matric)),
            React.createElement("td", null, mc.faculty_name || 'N/A', React.createElement("div", { className: "row-meta" }, mc.department_name || 'N/A')),
            React.createElement("td", null, mc.course_code || '—'),
            React.createElement("td", null, React.createElement("span", { className: "status-pill urgent" }, mc.sanction_type ? mc.sanction_type.replace('_', ' ') : 'Resolution Pending'),
              mc.duration_semesters && React.createElement("div", { className: "row-meta" }, `${mc.duration_semesters} Semesters`)
            ),
            React.createElement("td", null, React.createElement("span", { className: `result-badge ${mc.result_status || 'normal'}` }, mc.result_status || 'normal')),
            React.createElement("td", { className: "no-print" }, React.createElement("button", {
              className: "ghost-button",
              style: { minHeight: 28, fontSize: 10 },
              onClick: () => { setActiveMemo(null); openCase(mc.id); }
            }, "Dossier"))
          )))
        )
      )
    )),

    // Ratification Decision Modal
    showRatifyModal && React.createElement(Modal, {
      title: "Update Senate Ratification Status",
      subtitle: activeMemo ? activeMemo.memo_number : '',
      close: () => setShowRatifyModal(false),
      width: "560px"
    }, React.createElement("form", { onSubmit: submitRatification },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Ratification Status", style: { gridColumn: '1/-1' } },
          React.createElement("select", {
            value: ratifyForm.status,
            onChange: e => setRatifyForm({ ...ratifyForm, status: e.target.value })
          },
            React.createElement("option", { value: "recommended_to_senate" }, "Recommended to Senate"),
            React.createElement("option", { value: "senate_approved" }, "Senate Approved (Ratified & Sanctions Enforced)"),
            React.createElement("option", { value: "senate_amended" }, "Senate Amended (Modified Recommendations)"),
            React.createElement("option", { value: "senate_rejected" }, "Senate Rejected (Returned to Committee)")
          )
        ),
        React.createElement(Field, { label: "Senate Regular Meeting Number" },
          React.createElement("input", {
            value: ratifyForm.meeting_number,
            onChange: e => setRatifyForm({ ...ratifyForm, meeting_number: e.target.value }),
            placeholder: "e.g. 168"
          })
        ),
        React.createElement(Field, { label: "Ratification Date" },
          React.createElement("input", {
            type: "date",
            value: ratifyForm.ratification_date || new Date().toISOString().split('T')[0],
            onChange: e => setRatifyForm({ ...ratifyForm, ratification_date: e.target.value })
          })
        ),
        React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
          React.createElement("label", null, "Senate Amendments / Ratification Minutes"),
          React.createElement("textarea", {
            value: ratifyForm.ratification_notes,
            onChange: e => setRatifyForm({ ...ratifyForm, ratification_notes: e.target.value }),
            placeholder: "Extract from Senate minutes regarding this submission..."
          })
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowRatifyModal(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" },
          React.createElement("i", { className: "fas fa-stamp" }), " Save Senate Decision"
        )
      )
    ))
  );
}

// --- Phase 2: Physical Evidence Locker & Barcode Chain-of-Custody ---
function EvidenceLocker({ api, notify, user, lookups, openCase }) {
  const [data, setData] = useState({ lockers: [], unassigned_incidents: [] });
  const [q, setQ] = useState('');
  const [showLodge, setShowLodge] = useState(false);
  const [showTransfer, setShowTransfer] = useState(false);
  const [showLedger, setShowLedger] = useState(false);
  const [activeLocker, setActiveLocker] = useState(null);
  const [custodyLogs, setCustodyLogs] = useState([]);
  const [lodgeForm, setLodgeForm] = useState({
    incident_id: '',
    locker_identifier: 'Safe-A1',
    shelf_drawer: 'Shelf 1, Drawer B',
    description: '',
    barcode_tag: '',
    custodian_id: user.id
  });
  const [transferForm, setTransferForm] = useState({
    evidence_locker_id: '',
    to_custodian_id: '',
    reason: 'committee_hearing',
    notes: ''
  });

  const loadData = () => {
    api('get_evidence_lockers', {}, 'get').then(r => setData(r.data || { lockers: [], unassigned_incidents: [] })).catch(() => {});
  };

  useEffect(() => {
    loadData();
  }, []);

  const handleLodge = async e => {
    e.preventDefault();
    if (!lodgeForm.incident_id) {
      notify('Please select an incident to attach this evidence');
      return;
    }
    try {
      await api('tag_evidence_locker', lodgeForm);
      notify('Evidence securely lodged in safe repository');
      setShowLodge(false);
      setLodgeForm({ incident_id: '', locker_identifier: 'Safe-A1', shelf_drawer: 'Shelf 1, Drawer B', description: '', barcode_tag: '', custodian_id: user.id });
      loadData();
    } catch (e) {
      notify(e.response?.data?.message || 'Error lodging evidence');
    }
  };

  const handleTransfer = async e => {
    e.preventDefault();
    try {
      await api('transfer_evidence_custody', transferForm);
      notify('Custody transfer logged and confirmed');
      setShowTransfer(false);
      loadData();
    } catch (e) {
      notify(e.response?.data?.message || 'Error recording custody transfer');
    }
  };

  const viewLedger = async locker => {
    setActiveLocker(locker);
    try {
      const res = await api('get_evidence_transfers', { evidence_locker_id: locker.id });
      setCustodyLogs(res.data || []);
      setShowLedger(true);
    } catch (e) {
      notify('Unable to load transfer ledger');
    }
  };

  const openTransferModal = locker => {
    setActiveLocker(locker);
    setTransferForm({
      evidence_locker_id: locker.id,
      to_custodian_id: lookups.staff_members?.[0]?.id || '',
      reason: 'committee_hearing',
      notes: ''
    });
    setShowTransfer(true);
  };

  const filtered = data.lockers.filter(l => JSON.stringify(l).toLowerCase().includes(q.toLowerCase()));
  const isOfficerOrAdmin = ['admin', 'exam_officer', 'committee'].includes(user.role);

  return React.createElement(React.Fragment, null,
    React.createElement(SectionHead, {
      eyebrow: "Forensics & Security",
      title: "Physical Evidence Safe Lockers",
      sub: "Locker bin inventory, unique barcode tracking, and audited multi-custodian chain of custody.",
      action: isOfficerOrAdmin && React.createElement("button", {
        className: "primary-button",
        onClick: () => setShowLodge(true)
      }, React.createElement("i", { className: "fas fa-shield-halved" }), " Lodge New Item")
    }),

    // Stat Grid
    React.createElement("div", { className: "stat-grid" },
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon blue" }, React.createElement("i", { className: "fas fa-vault" })),
        React.createElement("div", null,
          React.createElement("strong", null, data.lockers.length),
          React.createElement("span", null, "Lodged Physical Items")
        )
      ),
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon gold" }, React.createElement("i", { className: "fas fa-box-open" })),
        React.createElement("div", null,
          React.createElement("strong", null, data.unassigned_incidents.length),
          React.createElement("span", null, "Incidents Awaiting Safe Deposit")
        )
      ),
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon green" }, React.createElement("i", { className: "fas fa-user-shield" })),
        React.createElement("div", null,
          React.createElement("strong", null, new Set(data.lockers.map(x => x.custodian_name).filter(Boolean)).size),
          React.createElement("span", null, "Active Item Custodians")
        )
      ),
      React.createElement("div", { className: "stat-card" },
        React.createElement("div", { className: "stat-icon blue" }, React.createElement("i", { className: "fas fa-timeline" })),
        React.createElement("div", null,
          React.createElement("strong", null, data.lockers.reduce((acc, l) => acc + (parseInt(l.transfer_count) || 0), 0)),
          React.createElement("span", null, "Custody Transfers Logged")
        )
      )
    ),

    // Panel with Table
    React.createElement("div", { className: "panel" },
      React.createElement("div", { className: "panel-head" },
        React.createElement("div", { className: "search-box", style: { minWidth: 320 } },
          React.createElement("i", { className: "fas fa-search" }),
          React.createElement("input", {
            value: q,
            onChange: e => setQ(e.target.value),
            placeholder: "Filter by barcode, student, locker, custodian..."
          })
        ),
        React.createElement("div", { className: "row-meta" }, `${filtered.length} locker record(s)`)
      ),
      React.createElement("div", { style: { overflowX: 'auto' } },
        React.createElement("table", { className: "modern-table" },
          React.createElement("thead", null, React.createElement("tr", null,
            React.createElement("th", null, "Barcode Tag"),
            React.createElement("th", null, "Locker Safe & Bin"),
            React.createElement("th", null, "Incident & Student"),
            React.createElement("th", null, "Description"),
            React.createElement("th", null, "Current Custodian"),
            React.createElement("th", null, "Status"),
            React.createElement("th", null, "Actions")
          )),
          React.createElement("tbody", null, filtered.map(lk => React.createElement("tr", { key: lk.id },
            React.createElement("td", null, React.createElement("span", { className: "barcode-tag" }, React.createElement("i", { className: "fas fa-barcode", style: { marginRight: 4 } }), lk.barcode_tag)),
            React.createElement("td", null, React.createElement("strong", null, lk.locker_identifier), React.createElement("div", { className: "row-meta" }, lk.shelf_drawer)),
            React.createElement("td", null, lk.student_name, React.createElement("div", { className: "row-meta" }, `${lk.reference_no} · ${lk.student_matric}`)),
            React.createElement("td", null, lk.description),
            React.createElement("td", null, lk.custodian_name || 'Central Safe'),
            React.createElement("td", null, React.createElement("span", { className: `status-pill ${lk.status === 'secured' ? 'completed' : 'pending'}` }, lk.status)),
            React.createElement("td", null, React.createElement("div", { style: { display: 'flex', gap: 6 } },
              React.createElement("button", {
                className: "ghost-button",
                style: { minHeight: 30, fontSize: 11 },
                onClick: () => viewLedger(lk)
              }, React.createElement("i", { className: "fas fa-timeline" }), " Ledger"),
              isOfficerOrAdmin && React.createElement("button", {
                className: "primary-button",
                style: { minHeight: 30, fontSize: 11 },
                onClick: () => openTransferModal(lk)
              }, React.createElement("i", { className: "fas fa-right-left" }), " Transfer")
            ))
          ))),
          !filtered.length && React.createElement("tr", null, React.createElement("td", { colSpan: 7, className: "empty-state" }, "No physical evidence items found."))
        )
      )
    ),

    // Lodge Evidence Modal
    showLodge && React.createElement(Modal, {
      title: "Lodge Physical Evidence into Safe",
      subtitle: "Register confiscated exam materials into physical custody locker",
      close: () => setShowLodge(false),
      width: "680px"
    }, React.createElement("form", { onSubmit: handleLodge },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Link to Incident Report", style: { gridColumn: '1/-1' } },
          React.createElement("select", {
            required: true,
            value: lodgeForm.incident_id,
            onChange: e => setLodgeForm({ ...lodgeForm, incident_id: e.target.value })
          },
            React.createElement("option", { value: "" }, "-- Select incident awaiting safe deposit --"),
            data.unassigned_incidents.map(ui => React.createElement("option", { key: ui.id, value: ui.id },
              `${ui.reference_no} — ${ui.student_name} (${ui.student_matric}) · ${ui.offense_category}`
            ))
          )
        ),
        React.createElement(Field, { label: "Physical Safe / Repository" },
          React.createElement("input", {
            required: true,
            value: lodgeForm.locker_identifier,
            onChange: e => setLodgeForm({ ...lodgeForm, locker_identifier: e.target.value }),
            placeholder: "e.g. Safe-A1"
          })
        ),
        React.createElement(Field, { label: "Shelf / Bin / Compartment" },
          React.createElement("input", {
            required: true,
            value: lodgeForm.shelf_drawer,
            onChange: e => setLodgeForm({ ...lodgeForm, shelf_drawer: e.target.value }),
            placeholder: "e.g. Shelf 2, Drawer B"
          })
        ),
        React.createElement(Field, { label: "Barcode Tag (optional, auto-generated if empty)" },
          React.createElement("input", {
            value: lodgeForm.barcode_tag,
            onChange: e => setLodgeForm({ ...lodgeForm, barcode_tag: e.target.value }),
            placeholder: "e.g. BUK-EV-2026-ABC123"
          })
        ),
        React.createElement(Field, { label: "Intake Custodian" },
          React.createElement("select", {
            value: lodgeForm.custodian_id,
            onChange: e => setLodgeForm({ ...lodgeForm, custodian_id: e.target.value })
          }, lookups.staff_members?.map(sm => React.createElement("option", { key: sm.id, value: sm.id }, `${sm.full_name} (${sm.role})`)))
        ),
        React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
          React.createElement("label", null, "Detailed Evidence Item Inventory"),
          React.createElement("textarea", {
            required: true,
            value: lodgeForm.description,
            onChange: e => setLodgeForm({ ...lodgeForm, description: e.target.value }),
            placeholder: "Describe the confiscated physical items in detail (e.g., folded formula sheets, scientific calculator with markings, smart device)..."
          })
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowLodge(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" },
          React.createElement("i", { className: "fas fa-shield-halved" }), " Lodge & Secure Evidence"
        )
      )
    )),

    // Transfer Custody Modal
    showTransfer && activeLocker && React.createElement(Modal, {
      title: "Record Chain-of-Custody Transfer",
      subtitle: `Transferring: ${activeLocker.barcode_tag} (${activeLocker.student_name})`,
      close: () => setShowTransfer(false),
      width: "560px"
    }, React.createElement("form", { onSubmit: handleTransfer },
      React.createElement("div", { className: "form-grid" },
        React.createElement(Field, { label: "Receiving Custodian", style: { gridColumn: '1/-1' } },
          React.createElement("select", {
            required: true,
            value: transferForm.to_custodian_id,
            onChange: e => setTransferForm({ ...transferForm, to_custodian_id: e.target.value })
          },
            React.createElement("option", { value: "" }, "-- Select recipient staff member --"),
            lookups.staff_members?.map(sm => React.createElement("option", { key: sm.id, value: sm.id }, `${sm.full_name} (${sm.role})`))
          )
        ),
        React.createElement(Field, { label: "Transfer Purpose", style: { gridColumn: '1/-1' } },
          React.createElement("select", {
            value: transferForm.reason,
            onChange: e => setTransferForm({ ...transferForm, reason: e.target.value })
          },
            React.createElement("option", { value: "committee_hearing" }, "Committee Hearing Presentation"),
            React.createElement("option", { value: "forensic_review" }, "Forensic & Hand-writing Inspection"),
            React.createElement("option", { value: "senate_presentation" }, "Senate Exhibition"),
            React.createElement("option", { value: "routine_safekeeping" }, "Routine Safe Transfer / Re-lodging")
          )
        ),
        React.createElement("div", { className: "field", style: { gridColumn: '1/-1' } },
          React.createElement("label", null, "Custody Notes & Instructions"),
          React.createElement("textarea", {
            value: transferForm.notes,
            onChange: e => setTransferForm({ ...transferForm, notes: e.target.value }),
            placeholder: "State condition of seal, handover observations, or expected return date..."
          })
        )
      ),
      React.createElement("div", { className: "modal-actions" },
        React.createElement("button", { type: "button", className: "ghost-button", onClick: () => setShowTransfer(false) }, "Cancel"),
        React.createElement("button", { type: "submit", className: "primary-button" },
          React.createElement("i", { className: "fas fa-check" }), " Confirm Custody Transfer"
        )
      )
    )),

    // Chain-of-Custody Ledger Modal
    showLedger && activeLocker && React.createElement(Modal, {
      title: `Chain-of-Custody Ledger: ${activeLocker.barcode_tag}`,
      subtitle: `${activeLocker.locker_identifier} · Incident: ${activeLocker.reference_no} (${activeLocker.student_name})`,
      close: () => setShowLedger(false),
      width: "680px"
    }, React.createElement("div", null,
      custodyLogs.length ? React.createElement("div", { className: "custody-timeline" },
        custodyLogs.map(log => React.createElement("div", { className: "custody-node", key: log.id },
          React.createElement("strong", { style: { fontSize: 13 } }, `${log.from_custodian_name || 'Intake Registry'} ➔ ${log.to_custodian_name}`),
          React.createElement("div", { style: { fontSize: 11, color: 'var(--muted)', marginTop: 2 } },
            `${new Date(log.transferred_at).toLocaleString()} · Reason: ${(log.reason || '').replace(/_/g, ' ')}`
          ),
          log.notes && React.createElement("p", { style: { fontSize: 12, margin: '6px 0 0', background: '#ffffff', border: '1px solid var(--line)', padding: 8, borderRadius: 8 } }, log.notes)
        ))
      ) : React.createElement("div", { className: "empty-state" }, "No transfer logs found for this item.")
    ))
  );
}

// --- Phase 2: Intelligence, Risk Heatmap & Collusion Network ---
function Intelligence({ api, notify, user, lookups, openCase }) {
  const [tab, setTab] = useState('heatmap');
  const [heatmap, setHeatmap] = useState({ by_faculty: [], by_venue: [], by_category: [], by_course: [] });
  const [collusion, setCollusion] = useState([]);
  const [radarQuery, setRadarQuery] = useState('');
  const [radarResult, setRadarResult] = useState(null);
  const [loadingRadar, setLoadingRadar] = useState(false);

  useEffect(() => {
    api('get_risk_heatmap', {}, 'get').then(r => setHeatmap(r.data || {})).catch(() => {});
    api('get_collusion_network', {}, 'get').then(r => setCollusion(r.data || [])).catch(() => {});
  }, []);

  const searchRadar = async e => {
    e.preventDefault();
    if (!radarQuery.trim()) return;
    setLoadingRadar(true);
    try {
      const res = await api('get_student_disciplinary_history', { matric_no: radarQuery.trim() });
      setRadarResult(res.data);
    } catch (err) {
      notify('Student not found or no records available');
      setRadarResult(null);
    } finally {
      setLoadingRadar(false);
    }
  };

  const maxFacIncidents = Math.max(...(heatmap.by_faculty?.map(f => parseInt(f.incident_count) || 0) || [1]), 1);
  const maxVenIncidents = Math.max(...(heatmap.by_venue?.map(v => parseInt(v.incident_count) || 0) || [1]), 1);

  return React.createElement(React.Fragment, null,
    React.createElement(SectionHead, {
      eyebrow: "Analytics & Detection",
      title: "Malpractice Intelligence & Risk Radar",
      sub: "Institutional risk distribution, visual collusion clustering, and repeat offender profiles."
    }),

    // Segmented Navigation Tabs
    React.createElement("div", { className: "segmented", style: { marginBottom: 20 } },
      React.createElement("button", {
        className: tab === 'heatmap' ? 'active' : '',
        onClick: () => setTab('heatmap')
      }, React.createElement("i", { className: "fas fa-fire", style: { marginRight: 6 } }), "Institutional Risk Heatmap"),
      React.createElement("button", {
        className: tab === 'collusion' ? 'active' : '',
        onClick: () => setTab('collusion')
      }, React.createElement("i", { className: "fas fa-users-rays", style: { marginRight: 6 } }), "Visual Collusion Network"),
      React.createElement("button", {
        className: tab === 'radar' ? 'active' : '',
        onClick: () => setTab('radar')
      }, React.createElement("i", { className: "fas fa-radar", style: { marginRight: 6 } }), "Repeat Offender Radar")
    ),

    // TAB 1: Institutional Risk Heatmap
    tab === 'heatmap' && React.createElement(React.Fragment, null,
      React.createElement("div", { className: "grid-2", style: { marginBottom: 20 } },
        // Faculty Risk Density
        React.createElement("div", { className: "panel" },
          React.createElement("div", { className: "panel-head" },
            React.createElement("div", null,
              React.createElement("div", { className: "panel-title" }, "Faculty Risk Density"),
              React.createElement("div", { className: "panel-sub" }, "Incident density and sanction rates by Faculty")
            )
          ),
          React.createElement("div", { className: "panel-body" },
            heatmap.by_faculty?.map(f => {
              const count = parseInt(f.incident_count) || 0;
              const pct = Math.round((count / maxFacIncidents) * 100);
              return React.createElement("div", { key: f.id, style: { marginBottom: 14 } },
                React.createElement("div", { style: { display: 'flex', justifyContent: 'space-between', fontSize: 12 } },
                  React.createElement("strong", null, f.name),
                  React.createElement("span", { className: "row-meta" }, `${count} incidents · ${f.sanction_count || 0} sanctions`)
                ),
                React.createElement("div", { className: "progress-bar-track" },
                  React.createElement("div", {
                    className: `progress-bar-fill ${count >= 5 ? 'fill-crimson' : (count >= 2 ? 'fill-gold' : 'fill-blue')}`,
                    style: { width: `${pct}%` }
                  })
                )
              );
            })
          )
        ),

        // Examination Hall / Venue Density
        React.createElement("div", { className: "panel" },
          React.createElement("div", { className: "panel-head" },
            React.createElement("div", null,
              React.createElement("div", { className: "panel-title" }, "Hall & Venue Infraction Density"),
              React.createElement("div", { className: "panel-sub" }, "Top examination halls with reported breaches")
            )
          ),
          React.createElement("div", { className: "panel-body" },
            heatmap.by_venue?.map((v, idx) => {
              const count = parseInt(v.incident_count) || 0;
              const pct = Math.round((count / maxVenIncidents) * 100);
              return React.createElement("div", { key: 'ven-' + idx, style: { marginBottom: 14 } },
                React.createElement("div", { style: { display: 'flex', justifyContent: 'space-between', fontSize: 12 } },
                  React.createElement("strong", null, v.venue),
                  React.createElement("span", { className: "row-meta" }, `${count} reports (${v.course_count || 1} courses)`)
                ),
                React.createElement("div", { className: "progress-bar-track" },
                  React.createElement("div", {
                    className: "progress-bar-fill fill-crimson",
                    style: { width: `${pct}%` }
                  })
                )
              );
            })
          )
        )
      ),

      // Offense Category & Top Courses
      React.createElement("div", { className: "grid-2" },
        React.createElement("div", { className: "panel" },
          React.createElement("div", { className: "panel-head" },
            React.createElement("div", { className: "panel-title" }, "Infraction Category Breakdown")
          ),
          React.createElement("div", { className: "panel-body" },
            heatmap.by_category?.map((c, idx) => React.createElement("div", {
              className: "list-row",
              key: 'cat-' + idx
            },
              React.createElement("div", { className: "row-title", style: { textTransform: 'capitalize' } }, (c.offense_category || '').replace('_', ' ')),
              React.createElement("span", { className: "status-pill urgent" }, `${c.incident_count} cases`)
            ))
          )
        ),
        React.createElement("div", { className: "panel" },
          React.createElement("div", { className: "panel-head" },
            React.createElement("div", { className: "panel-title" }, "Most Affected Courses")
          ),
          React.createElement("div", { className: "panel-body" },
            heatmap.by_course?.map((cr, idx) => React.createElement("div", {
              className: "list-row",
              key: 'crs-' + idx
            },
              React.createElement("div", null,
                React.createElement("div", { className: "row-title" }, cr.code),
                React.createElement("div", { className: "row-meta" }, cr.title)
              ),
              React.createElement("span", { className: "status-pill open" }, `${cr.incident_count} reports`)
            ))
          )
        )
      )
    ),

    // TAB 2: Visual Collusion & Seating Network
    tab === 'collusion' && React.createElement(React.Fragment, null,
      React.createElement("div", { style: { marginBottom: 16 } },
        React.createElement("p", { style: { fontSize: 13, color: 'var(--muted)', margin: 0 } },
          "The collusion detector automatically isolates concurrent exam malpractice occurrences in the exact same venue and course session, uncovering organized syndicates and neighboring co-accused students."
        )
      ),
      collusion.length ? collusion.map((cl, idx) => React.createElement("div", {
        className: "collusion-cluster",
        key: 'cluster-' + idx
      },
        React.createElement("div", { className: "collusion-cluster-head" },
          React.createElement("div", null,
            React.createElement("strong", { style: { fontSize: 15, color: 'var(--buk-navy)' } }, `${cl.course_code} · ${cl.venue}`),
            React.createElement("div", { className: "row-meta", style: { marginTop: 3 } },
              `Exam Date: ${cl.incident_date} · Session: ${cl.session_time || 'Regular Session'}`
            )
          ),
          React.createElement("span", { className: "cluster-pill" },
            React.createElement("i", { className: "fas fa-users", style: { marginRight: 5 } }),
            `${cl.cluster_size} Co-accused Students Cluster`
          )
        ),
        React.createElement("div", { style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 12 } },
          cl.co_accused?.map(st => React.createElement("div", {
            key: st.id,
            style: { border: '1px solid var(--line)', borderRadius: 10, padding: 12, background: '#ffffff' }
          },
            React.createElement("div", { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
              React.createElement("strong", { style: { fontSize: 12 } }, st.student_name),
              st.seat_number && React.createElement("span", {
                style: { background: 'var(--buk-gold)', color: '#062f4a', fontWeight: 800, fontSize: 10, padding: '2px 6px', borderRadius: 4 }
              }, `Seat ${st.seat_number}`)
            ),
            React.createElement("div", { className: "row-meta", style: { marginTop: 4 } }, st.student_matric),
            React.createElement("div", { style: { fontSize: 11, color: '#be123c', marginTop: 4, fontWeight: 700 } }, (st.offense_category || '').replace('_', ' ')),
            React.createElement("div", { style: { marginTop: 6 } },
              React.createElement("span", { className: `status-pill ${st.status}` }, st.status)
            )
          ))
        )
      )) : React.createElement("div", { className: "panel" },
        React.createElement("div", { className: "empty-state" },
          React.createElement("i", { className: "fas fa-shield-check fa-2x", style: { color: 'var(--success)' } }),
          React.createElement("p", null, "No concurrent multi-student collusion clusters detected in the current session.")
        )
      )
    ),

    // TAB 3: Repeat Offender Radar
    tab === 'radar' && React.createElement(React.Fragment, null,
      React.createElement("div", { className: "panel", style: { marginBottom: 20 } },
        React.createElement("div", { className: "panel-body" },
          React.createElement("form", { onSubmit: searchRadar, style: { display: 'flex', gap: 10 } },
            React.createElement("div", { className: "search-box", style: { flex: 1 } },
              React.createElement("i", { className: "fas fa-id-card" }),
              React.createElement("input", {
                required: true,
                value: radarQuery,
                onChange: e => setRadarQuery(e.target.value),
                placeholder: "Enter Student Matriculation Number (e.g. CST/21/COM/00142)..."
              })
            ),
            React.createElement("button", { type: "submit", className: "primary-button" },
              React.createElement("i", { className: "fas fa-radar" }), " Scan Disciplinary History"
            )
          )
        )
      ),

      loadingRadar ? React.createElement("div", { className: "empty-state" },
        React.createElement("i", { className: "fas fa-circle-notch fa-spin fa-2x" }),
        React.createElement("p", null, "Scanning central misconduct radar registry...")
      ) : radarResult && React.createElement("div", null,
        React.createElement("div", { className: "panel", style: { marginBottom: 18 } },
          React.createElement("div", { className: "panel-head" },
            React.createElement("div", null,
              React.createElement("div", { className: "panel-title" }, radarResult.student?.full_name || radarResult.matric_no),
              React.createElement("div", { className: "panel-sub" }, `Matric: ${radarResult.matric_no} · ${radarResult.student?.faculty_name || 'N/A'} · ${radarResult.student?.department_name || 'N/A'}`)
            ),
            React.createElement("span", {
              className: `status-pill ${radarResult.total_incidents > 1 ? 'urgent' : (radarResult.total_incidents === 1 ? 'pending' : 'completed')}`
            }, radarResult.risk_badge)
          ),
          React.createElement("div", { className: "panel-body" },
            React.createElement("div", { className: "form-grid" },
              React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Total Misconduct Incidents"), React.createElement("strong", { className: "row-title" }, `${radarResult.total_incidents} Infraction(s)`)),
              React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Active Sanctions on File"), React.createElement("strong", { className: "row-title" }, `${radarResult.active_sanctions} Sanction(s)`)),
              React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Repeat Offender Status"), React.createElement("strong", { className: "row-title", style: { color: radarResult.is_repeat_offender ? '#be123c' : '#15803d' } }, radarResult.is_repeat_offender ? "YES (Flagged Repeat Offender)" : "NO (First Offense / Clean)")),
              React.createElement("div", null, React.createElement("small", { className: "row-meta" }, "Student Level"), React.createElement("strong", { className: "row-title" }, `${radarResult.student?.level || 'N/A'} Level`))
            )
          )
        ),

        React.createElement("div", { className: "panel" },
          React.createElement("div", { className: "panel-head" },
            React.createElement("div", { className: "panel-title" }, "Chronological Misconduct Dossier")
          ),
          React.createElement("div", { style: { overflowX: 'auto' } },
            React.createElement("table", { className: "modern-table" },
              React.createElement("thead", null, React.createElement("tr", null,
                React.createElement("th", null, "Ref / Case"),
                React.createElement("th", null, "Date"),
                React.createElement("th", null, "Course"),
                React.createElement("th", null, "Offense Category"),
                React.createElement("th", null, "Venue"),
                React.createElement("th", null, "Case Stage"),
                React.createElement("th", null, "Sanction Imposed"),
                React.createElement("th", null, "Result Status")
              )),
              React.createElement("tbody", null, radarResult.incidents?.map(it => React.createElement("tr", { key: it.id },
                React.createElement("td", null, React.createElement("strong", null, it.case_no || it.reference_no)),
                React.createElement("td", null, it.incident_date || '—'),
                React.createElement("td", null, it.course_code || '—'),
                React.createElement("td", null, (it.offense_category || '').replace('_', ' ')),
                React.createElement("td", null, it.venue || '—'),
                React.createElement("td", null, it.case_stage ? React.createElement("span", { className: `status-pill ${it.case_stage}` }, it.case_stage) : 'Pending Review'),
                React.createElement("td", null, it.sanction_type ? React.createElement("span", { className: "status-pill urgent" }, it.sanction_type) : 'None'),
                React.createElement("td", null, it.result_status ? React.createElement("span", { className: `result-badge ${it.result_status}` }, it.result_status) : '—')
              ))),
              !radarResult.incidents?.length && React.createElement("tr", null, React.createElement("td", { colSpan: 8, className: "empty-state" }, "No examination misconduct infractions on record for this student."))
            )
          )
        )
      )
    )
  );
}

function Field({ label, children, style }) {
  return React.createElement("div", {
    className: "field",
    style: style
  }, React.createElement("label", null, label), children);
}

ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(App, null));
