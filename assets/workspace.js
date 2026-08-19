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
  calendar: 'fa-calendar-check',
  reports: 'fa-chart-line',
  announcements: 'fa-bullhorn',
  settings: 'fa-sliders',
  drafts: 'fa-file-pen',
  operations: 'fa-diagram-project'
};
function App() {
  const [user, setUser] = useState(null),
    [csrf, setCsrf] = useState(''),
    [data, setData] = useState(null),
    [page, setPage] = useState('overview'),
    [modal, setModal] = useState(null),
    [toast, setToast] = useState(''),
    [mobile, setMobile] = useState(false),
    [query, setQuery] = useState(''),
    [results, setResults] = useState([]),
    [caseId, setCaseId] = useState(null),
    [caseData, setCaseData] = useState(null),
    [lookups, setLookups] = useState({
      departments: [],
      faculties: [],
      courses: [],
      committee_members: [],
      sessions: [],
      venues: []
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
    setTimeout(() => setToast(''), 3400);
  };
  useEffect(() => {
    axios.post(`${API}?action=check_auth`).then(async r => {
      if (!r.data.isAuthenticated) {
        location.href = 'index.php';
        return;
      }
      setUser(r.data.user);
      setCsrf(r.data.csrf_token);
      const [w, l] = await Promise.all([axios.post(`${API}?action=get_workspace_data`), axios.post(`${API}?action=get_lookups`)]);
      setData(w.data.data);
      setLookups(l.data.data);
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
    const r = await api('get_workspace_data');
    setData(r.data);
  };
  const go = p => {
    if (p === 'operations') { location.href = 'operations.php'; return; }
    setPage(p);
    setMobile(false);
    setCaseId(null);
    setCaseData(null);
  };
  const openCase = async id => {
    const r = await api('get_case_timeline', {
      case_id: id
    });
    setCaseId(id);
    setCaseData(r.data);
    setPage('case');
  };
  if (!user || !data) return React.createElement("div", {
    style: {
      minHeight: '100vh',
      display: 'grid',
      placeItems: 'center',
      background: '#f4f7fb',
      color: '#0b5c8e'
    }
  }, React.createElement("i", {
    className: "fas fa-circle-notch fa-spin fa-2x"
  }));
  const roleLabel = {
    admin: 'System Administrator',
    exam_officer: 'Examination Officer',
    invigilator: 'Invigilator',
    hod: 'HOD / Dean',
    committee: 'Committee Member'
  }[user.role] || user.role;
  const nav = [['overview', 'Overview'], ['work', 'My Work'], ['incidents', 'Incidents'], ['cases', 'Cases'], ['hearings', 'Hearings'], ['calendar', 'Calendar'], ['reports', 'Reports'], ['operations', 'Operations']];
  if (['invigilator', 'exam_officer', 'admin'].includes(user.role)) nav.push(['drafts', 'Drafts']);
  if (user.role === 'admin') nav.push(['announcements', 'Announcements'], ['settings', 'Settings']);
  return React.createElement("div", {
    className: "modern-shell"
  }, React.createElement("aside", {
    className: `modern-sidebar ${mobile ? 'open' : ''}`
  }, React.createElement("div", {
    className: "brand-lockup"
  }, React.createElement("img", {
    src: "assets/buk-logo.png"
  }), React.createElement("div", null, React.createElement("strong", null, "BUK", React.createElement("span", {
    style: {
      color: 'var(--buk-gold-light)'
    }
  }, " EIMS")), React.createElement("small", null, "BAYERO UNIVERSITY KANO"))), React.createElement("nav", {
    className: "modern-nav"
  }, React.createElement("div", {
    className: "nav-label"
  }, "Workspace"), nav.map(([id, label]) => React.createElement("button", {
    key: id,
    className: page === id ? 'active' : '',
    onClick: () => go(id)
  }, React.createElement("i", {
    className: `fas ${iconMap[id]}`
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
    style: {
      minWidth: 0
    }
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
    className: "modern-topbar"
  }, React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 14
    }
  }, React.createElement("button", {
    className: "icon-button mobile-menu",
    onClick: () => setMobile(!mobile)
  }, React.createElement("i", {
    className: "fas fa-bars"
  })), React.createElement("div", null, React.createElement("div", {
    className: "crumb"
  }, "BUK EIMS / ", page === 'overview' ? 'workspace' : page), React.createElement("div", {
    className: "page-heading"
  }, page === 'overview' ? `Good morning, ${user.full_name?.split(' ')[0]}` : page === 'case' ? 'Case workspace' : page.replace('_', ' ')))), React.createElement("div", {
    className: "top-actions"
  }, React.createElement("div", {
    className: "search-box"
  }, React.createElement("i", {
    className: "fas fa-search",
    style: {
      color: 'var(--muted)'
    }
  }), React.createElement("input", {
    value: query,
    onChange: e => setQuery(e.target.value),
    placeholder: "Search cases, incidents..."
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
    style: {
      fontSize: 12
    }
  }, x.ref), React.createElement("div", {
    style: {
      fontSize: 11,
      color: 'var(--muted)',
      marginTop: 3
    }
  }, x.title, " · ", x.meta))))), React.createElement("button", {
    className: "icon-button",
    onClick: () => {
      go('overview');
      document.getElementById('notifications')?.scrollIntoView({
        behavior: 'smooth'
      });
    }
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
  }, page === 'overview' && React.createElement(Overview, {
    data: data,
    user: user,
    go: go,
    openCase: openCase,
    notify: notify,
    api: api,
    refresh: refresh
  }), " ", page === 'work' && React.createElement(Tasks, {
    data: data,
    api: api,
    refresh: refresh,
    notify: notify
  }), " ", page === 'incidents' && React.createElement(Incidents, {
    api: api,
    openCase: openCase,
    user: user,
    lookups: lookups,
    notify: notify
  }), " ", page === 'cases' && React.createElement(Cases, {
    api: api,
    openCase: openCase,
    user: user
  }), " ", page === 'case' && React.createElement(CaseWorkspace, {
    data: caseData,
    user: user,
    api: api,
    notify: notify,
    onBack: () => go('cases'),
    refresh: async () => openCase(caseId)
  }), " ", page === 'hearings' && React.createElement(Hearings, {
    data: data,
    lookups: lookups,
    api: api,
    notify: notify
  }), " ", page === 'calendar' && React.createElement(Calendar, {
    api: api
  }), " ", page === 'reports' && React.createElement(Reports, {
    api: api
  }), " ", page === 'drafts' && React.createElement(Drafts, {
    api: api,
    notify: notify,
    go: go
  }), " ", page === 'announcements' && React.createElement(Announcements, {
    api: api,
    notify: notify
  }), " ", page === 'settings' && React.createElement(Settings, {
    api: api,
    user: user,
    notify: notify
  }))), modal && React.createElement(Modal, {
    ...modal,
    close: () => setModal(null)
  }), " ", toast && React.createElement("div", {
    className: "toast"
  }, React.createElement("i", {
    className: "fas fa-circle-check",
    style: {
      color: 'var(--buk-gold-light)',
      marginRight: 8
    }
  }), toast));
}
function SectionHead({
  eyebrow,
  title,
  sub,
  action
}) {
  return React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'flex-end',
      gap: 20,
      marginBottom: 17
    }
  }, React.createElement("div", null, React.createElement("div", {
    className: "crumb",
    style: {
      textTransform: 'uppercase',
      letterSpacing: '.12em'
    }
  }, eyebrow), React.createElement("h2", {
    style: {
      margin: '5px 0 0',
      fontSize: 22
    }
  }, title), sub && React.createElement("div", {
    style: {
      fontSize: 12,
      color: 'var(--muted)',
      marginTop: 6
    }
  }, sub)), action);
}
function Overview({
  data,
  user,
  go,
  openCase,
  notify,
  api,
  refresh
}) {
  const s = data.stats || {};
  return React.createElement(React.Fragment, null, React.createElement("div", {
    className: "hero-strip"
  }, React.createElement("div", {
    style: {
      position: 'relative',
      zIndex: 1
    }
  }, React.createElement("div", {
    style: {
      fontSize: 11,
      fontWeight: 800,
      letterSpacing: '.12em',
      textTransform: 'uppercase',
      color: 'var(--buk-gold-light)'
    }
  }, "Institutional integrity workspace"), React.createElement("h2", null, "One clear view of every action that matters."), React.createElement("p", null, "Manage reports, review tasks, hearings, communications and case decisions with a modern BUK workflow."), React.createElement("div", {
    className: "quick-actions"
  }, React.createElement("button", {
    className: "primary-button",
    onClick: () => go(user.role === 'invigilator' ? 'drafts' : 'work')
  }, React.createElement("i", {
    className: "fas fa-bolt"
  }), user.role === 'invigilator' ? 'Continue a report' : 'Open my work'), React.createElement("button", {
    className: "ghost-button",
    onClick: () => go('incidents')
  }, React.createElement("i", {
    className: "fas fa-magnifying-glass"
  }), "Browse records"), ['admin', 'exam_officer', 'invigilator'].includes(user.role) && React.createElement("button", {
    className: "ghost-button",
    onClick: () => go('drafts')
  }, React.createElement("i", {
    className: "fas fa-file-pen"
  }), "Drafts")))), React.createElement("div", {
    className: "stat-grid"
  }, [['incidents', 'Incidents', 'fa-file-circle-exclamation'], ['open_cases', 'Open cases', 'fa-folder-open'], ['resolved_cases', 'Resolved cases', 'fa-circle-check'], ['overdue_tasks', 'Overdue tasks', 'fa-clock']].map(([key, label, ico]) => React.createElement("div", {
    className: "stat-card",
    key: key
  }, React.createElement("div", {
    className: "stat-top"
  }, React.createElement("span", null, label), React.createElement("div", {
    className: "stat-icon"
  }, React.createElement("i", {
    className: `fas ${ico}`
  }))), React.createElement("div", {
    className: "stat-value"
  }, s[key] ?? 0), React.createElement("div", {
    className: "stat-meta"
  }, "Live workspace summary")))), React.createElement("div", {
    className: "grid-2"
  }, React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", null, React.createElement("div", {
    className: "panel-title"
  }, "My next actions"), React.createElement("div", {
    className: "panel-sub"
  }, "Prioritized tasks across your workflow")), React.createElement("button", {
    className: "ghost-button",
    onClick: () => go('work')
  }, "View all")), React.createElement("div", {
    className: "panel-body"
  }, data.tasks?.length ? data.tasks.slice(0, 5).map(t => React.createElement("div", {
    className: "list-row",
    key: t.id
  }, React.createElement("div", null, React.createElement("div", {
    className: "row-title"
  }, t.title), React.createElement("div", {
    className: "row-meta"
  }, t.case_no || t.reference_no || 'General task', " · ", t.due_date ? new Date(t.due_date).toLocaleDateString() : 'No due date')), React.createElement("span", {
    className: `status-pill ${t.priority === 'urgent' ? 'urgent' : t.status}`
  }, t.priority))) : React.createElement("div", {
    className: "empty-state"
  }, React.createElement("i", {
    className: "fas fa-check-double"
  }), React.createElement("div", null, "You are all caught up.")))), React.createElement("div", {
    className: "panel",
    id: "notifications"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", null, React.createElement("div", {
    className: "panel-title"
  }, "Notifications"), React.createElement("div", {
    className: "panel-sub"
  }, "Recent updates and assignments")), React.createElement("button", {
    className: "ghost-button",
    onClick: async () => { await api('mark_all_notifications_read'); await refresh(); notify('Notifications marked as read'); }
  }, "Mark read")), React.createElement("div", {
    className: "panel-body"
  }, data.notifications?.length ? data.notifications.slice(0, 5).map(n => React.createElement("div", {
    className: "list-row",
    key: n.id
  }, React.createElement("div", null, React.createElement("div", {
    className: "row-title"
  }, n.title), React.createElement("div", {
    className: "row-meta"
  }, n.message)), !n.is_read && React.createElement("span", {
    className: "status-pill open"
  }, "New"))) : React.createElement("div", {
    className: "empty-state"
  }, React.createElement("i", {
    className: "far fa-bell-slash"
  }), React.createElement("div", null, "No new notifications."))))), React.createElement("div", {
    className: "panel",
    style: {
      marginTop: 18
    }
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", null, React.createElement("div", {
    className: "panel-title"
  }, "Institution updates"), React.createElement("div", {
    className: "panel-sub"
  }, "Announcements for your role")), React.createElement("button", {
    className: "ghost-button",
    onClick: () => go('announcements')
  }, "See announcements")), React.createElement("div", {
    className: "panel-body"
  }, data.announcements?.length ? data.announcements.map(a => React.createElement("div", {
    className: "list-row",
    key: a.id
  }, React.createElement("div", null, React.createElement("div", {
    className: "row-title"
  }, a.title), React.createElement("div", {
    className: "row-meta"
  }, a.body)), React.createElement("span", {
    className: "row-meta"
  }, new Date(a.created_at).toLocaleDateString()))) : React.createElement("div", {
    className: "empty-state"
  }, "No announcements."))));
}
function Tasks({
  data,
  api,
  refresh,
  notify
}) {
  const [tasks, setTasks] = useState(data.tasks || []);
  useEffect(() => {
    api('get_tasks').then(r => setTasks(r.data || []));
  }, []);
  const update = async (t, status) => {
    await api('update_task_status', {
      task_id: t.id,
      status
    });
    setTasks(x => x.map(y => y.id === t.id ? {
      ...y,
      status
    } : y));
    notify('Task updated');
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
    style: {
      marginLeft: 8
    }
  }, tasks.filter(t => t.status !== 'completed').length, " open")), React.createElement("button", {
    className: "ghost-button",
    onClick: refresh
  }, React.createElement("i", {
    className: "fas fa-rotate"
  }), "Refresh")), React.createElement("div", {
    className: "panel-body"
  }, tasks.length ? React.createElement("table", {
    className: "modern-table"
  }, React.createElement("thead", null, React.createElement("tr", null, React.createElement("th", null, "Task"), React.createElement("th", null, "Reference"), React.createElement("th", null, "Priority"), React.createElement("th", null, "Due"), React.createElement("th", null, "Status"), React.createElement("th", null))), React.createElement("tbody", null, tasks.map(t => React.createElement("tr", {
    key: t.id
  }, React.createElement("td", null, React.createElement("strong", null, t.title), React.createElement("div", {
    className: "row-meta"
  }, t.description)), React.createElement("td", null, t.case_no || t.reference_no || '—'), React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${t.priority === 'urgent' ? 'urgent' : t.priority}`
  }, t.priority)), React.createElement("td", null, t.due_date ? new Date(t.due_date).toLocaleDateString() : '—'), React.createElement("td", null, React.createElement("span", {
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
  }, React.createElement("option", {
    value: "open"
  }, "Open"), React.createElement("option", {
    value: "in_progress"
  }, "In progress"), React.createElement("option", {
    value: "completed"
  }, "Complete"), React.createElement("option", {
    value: "dismissed"
  }, "Dismiss"))))))) : React.createElement("div", {
    className: "empty-state"
  }, React.createElement("i", {
    className: "fas fa-check-double"
  }), React.createElement("div", null, "No assigned tasks.")))));
}
function Incidents({
  api,
  openCase,
  user,
  lookups,
  notify
}) {
  const [rows, setRows] = useState([]),
    [q, setQ] = useState(''),
    [show, setShow] = useState(false),
    [step, setStep] = useState(1),
    [form, setForm] = useState({});
  useEffect(() => {
    api('get_incidents').then(r => setRows(r.data || []));
  }, []);
  const filtered = rows.filter(r => JSON.stringify(r).toLowerCase().includes(q.toLowerCase()));
  const set = (k, v) => setForm({
    ...form,
    [k]: v
  });
  const submit = async e => {
    e.preventDefault();
    const fd = new FormData();
    Object.entries(form).forEach(([k, v]) => fd.append(k, v ?? ''));
    const file = e.currentTarget.querySelector('input[type="file"]')?.files?.[0];
    if (file) fd.append('evidence', file);
    await api('report_incident', fd);
    notify('Incident submitted successfully');
    setShow(false);
    setStep(1);
    setForm({});
    api('get_incidents').then(r => setRows(r.data || []));
  };
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Records",
    title: "Incident registry",
    sub: "Search, review and act on examination incident reports.",
    action: ['invigilator', 'exam_officer', 'admin'].includes(user.role) && React.createElement("button", {
      className: "primary-button",
      onClick: () => setShow(true)
    }, React.createElement("i", {
      className: "fas fa-plus"
    }), "New incident")
  }), React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "search-box",
    style: {
      minWidth: 320
    }
  }, React.createElement("i", {
    className: "fas fa-filter"
  }), React.createElement("input", {
    value: q,
    onChange: e => setQ(e.target.value),
    placeholder: "Filter by reference, student, venue..."
  })), React.createElement("div", {
    className: "row-meta"
  }, filtered.length, " records")), React.createElement("div", {
    style: {
      overflowX: 'auto'
    }
  }, React.createElement("table", {
    className: "modern-table"
  }, React.createElement("thead", null, React.createElement("tr", null, React.createElement("th", null, "Reference"), React.createElement("th", null, "Student"), React.createElement("th", null, "Course"), React.createElement("th", null, "Offence"), React.createElement("th", null, "Status"), React.createElement("th", null, "Submitted"), React.createElement("th", null))), React.createElement("tbody", null, filtered.map(r => React.createElement("tr", {
    key: r.id
  }, React.createElement("td", null, React.createElement("strong", null, r.reference_no)), React.createElement("td", null, r.student_name, React.createElement("div", {
    className: "row-meta"
  }, r.student_matric)), React.createElement("td", null, r.course_code), React.createElement("td", null, r.offence_type.replace('_', ' ')), React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${r.status}`
  }, r.status.replace('_', ' '))), React.createElement("td", null, new Date(r.created_at).toLocaleDateString()), React.createElement("td", null, React.createElement("button", {
    className: "ghost-button",
    onClick: () => notify('Incident details are available in the record view')
  }, "View")))))), !filtered.length && React.createElement("div", {
    className: "empty-state"
  }, "No matching incidents found."))), show && React.createElement("div", {
    className: "modal-backdrop"
  }, React.createElement("div", {
    className: "modern-modal"
  }, React.createElement("div", {
    className: "modal-head"
  }, React.createElement("div", null, React.createElement("h3", null, "New incident report"), React.createElement("div", {
    className: "panel-sub"
  }, "Step ", step, " of 3 · Save a clear, complete record.")), React.createElement("button", {
    className: "icon-button",
    onClick: () => setShow(false)
  }, React.createElement("i", {
    className: "fas fa-times"
  }))), React.createElement("form", {
    onSubmit: submit
  }, React.createElement("div", {
    className: "modal-body"
  }, step === 1 && React.createElement("div", {
    className: "form-grid"
  }, React.createElement(Field, {
    label: "Student name"
  }, React.createElement("input", {
    required: true,
    value: form.student_name || '',
    onChange: e => set('student_name', e.target.value)
  })), React.createElement(Field, {
    label: "Matriculation number"
  }, React.createElement("input", {
    required: true,
    value: form.student_matric || '',
    onChange: e => set('student_matric', e.target.value)
  })), React.createElement(Field, {
    label: "Course"
  }, React.createElement("select", {
    required: true,
    value: form.course_id || '',
    onChange: e => set('course_id', e.target.value)
  }, React.createElement("option", {
    value: ""
  }, "Select course"), lookups.courses.map(c => React.createElement("option", {
    value: c.id,
    key: c.id
  }, c.code, " — ", c.title)))), React.createElement(Field, {
    label: "Department"
  }, React.createElement("select", {
    required: true,
    value: form.student_dept_id || '',
    onChange: e => set('student_dept_id', e.target.value)
  }, React.createElement("option", {
    value: ""
  }, "Select department"), lookups.departments.map(d => React.createElement("option", {
    value: d.id,
    key: d.id
  }, d.name)))), React.createElement(Field, {
    label: "Exam date"
  }, React.createElement("input", {
    type: "date",
    required: true,
    value: form.exam_date || '',
    onChange: e => set('exam_date', e.target.value)
  })), React.createElement(Field, {
    label: "Student level"
  }, React.createElement("select", {
    required: true,
    value: form.student_level || '100',
    onChange: e => set('student_level', e.target.value)
  }, ['100', '200', '300', '400', '500'].map(x => React.createElement("option", { key: x }, x)))), React.createElement(Field, {
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
  }, React.createElement("option", null, "First"), React.createElement("option", null, "Second"))), React.createElement(Field, {
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
    key: x
  }, x)))), React.createElement("div", {
    className: "field",
    style: {
      gridColumn: '1/-1'
    }
  }, React.createElement("label", null, "Incident narrative"), React.createElement("textarea", {
    required: true,
    value: form.description || '',
    onChange: e => set('description', e.target.value),
    placeholder: "Describe what happened, who was present, and what was observed."
  }))), step === 3 && React.createElement("div", null, React.createElement("div", {
    className: "panel",
    style: {
      background: '#f8fafc',
      boxShadow: 'none'
    }
  }, React.createElement("div", {
    className: "panel-body"
  }, React.createElement("div", {
    className: "row-title"
  }, "Review before submission"), React.createElement("p", {
    className: "row-meta"
  }, form.student_name || 'Student', " · ", form.offence_type || 'Offence', " · ", form.venue || 'Venue'), React.createElement("p", {
    style: {
      fontSize: 12,
      lineHeight: 1.7
    }
  }, form.description || 'No narrative entered.'))), React.createElement("div", {
    className: "field",
    style: {
      marginTop: 16
    }
  }, React.createElement("label", null, "Evidence file (optional)"), React.createElement("input", {
    type: "file",
    name: "evidence"
  })))), React.createElement("div", {
    className: "modal-head",
    style: {
      justifyContent: 'flex-end',
      gap: 9
    }
  }, step > 1 && React.createElement("button", {
    type: "button",
    className: "ghost-button",
    onClick: () => setStep(step - 1)
  }, "Back"), step < 3 ? React.createElement("button", {
    type: "button",
    className: "primary-button",
    onClick: () => setStep(step + 1)
  }, "Continue ", React.createElement("i", {
    className: "fas fa-arrow-right"
  })) : React.createElement("button", {
    className: "primary-button"
  }, React.createElement("i", {
    className: "fas fa-paper-plane"
  }), "Submit report"))))));
}
function Cases({
  api,
  openCase,
  user
}) {
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
  }, React.createElement("i", {
    className: "fas fa-search"
  }), React.createElement("input", {
    value: q,
    onChange: e => setQ(e.target.value),
    placeholder: "Search case number or student..."
  })), React.createElement("div", {
    className: "row-meta"
  }, filtered.length, " cases")), React.createElement("div", {
    style: {
      overflowX: 'auto'
    }
  }, React.createElement("table", {
    className: "modern-table"
  }, React.createElement("thead", null, React.createElement("tr", null, React.createElement("th", null, "Case"), React.createElement("th", null, "Student"), React.createElement("th", null, "Offence"), React.createElement("th", null, "Stage"), React.createElement("th", null, "Priority"), React.createElement("th", null, "Updated"), React.createElement("th", null))), React.createElement("tbody", null, filtered.map(r => React.createElement("tr", {
    key: r.id
  }, React.createElement("td", null, React.createElement("strong", null, r.case_no), React.createElement("div", {
    className: "row-meta"
  }, r.reference_no)), React.createElement("td", null, r.student_name, React.createElement("div", {
    className: "row-meta"
  }, r.student_matric)), React.createElement("td", null, r.offence_type.replace('_', ' ')), React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${r.stage}`
  }, r.stage.replace('_', ' '))), React.createElement("td", null, React.createElement("span", {
    className: `status-pill ${r.priority}`
  }, r.priority)), React.createElement("td", null, new Date(r.updated_at).toLocaleDateString()), React.createElement("td", null, React.createElement("button", {
    className: "primary-button",
    onClick: () => openCase(r.id)
  }, "Open case")))))), !filtered.length && React.createElement("div", {
    className: "empty-state"
  }, "No cases are available in your workspace."))));
}
function CaseWorkspace({
  data,
  user,
  api,
  notify,
  onBack,
  refresh
}) {
  const [comment, setComment] = useState(''),
    [grounds, setGrounds] = useState(''),
    [showAppeal, setShowAppeal] = useState(false);
  if (!data) return React.createElement("div", {
    className: "empty-state"
  }, "Loading case...");
  const c = data.case;
  const addComment = async () => {
    if (!comment.trim()) return;
    await api('add_case_comment', {
      case_id: c.id,
      body: comment,
      visibility: 'internal'
    });
    setComment('');
    notify('Comment added');
    refresh();
  };
  const appeal = async () => {
    await api('submit_appeal', {
      case_id: c.id,
      grounds
    });
    setGrounds('');
    setShowAppeal(false);
    notify('Appeal submitted');
    refresh();
  };
  return React.createElement(React.Fragment, null, React.createElement(SectionHead, {
    eyebrow: "Case workspace",
    title: c.case_no,
    sub: `${c.reference_no} · ${c.student_name} · ${c.course_code}`,
    action: React.createElement("button", {
      className: "ghost-button",
      onClick: onBack
    }, React.createElement("i", {
      className: "fas fa-arrow-left"
    }), "Back to cases")
  }), React.createElement("div", {
    className: "grid-2"
  }, React.createElement("div", null, React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", null, React.createElement("div", {
    className: "panel-title"
  }, "Case timeline"), React.createElement("div", {
    className: "panel-sub"
  }, "Every meaningful action in chronological order")), React.createElement("span", {
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
  }), React.createElement("strong", null, l.action.replace('_', ' '), " · ", l.actor_name), React.createElement("p", null, l.note || 'Workflow event recorded.', React.createElement("br", null), React.createElement("small", null, new Date(l.logged_at).toLocaleString())))), data.comments?.map(l => React.createElement("div", {
    className: "timeline-item",
    key: 'c' + l.id
  }, React.createElement("span", {
    className: "timeline-dot",
    style: {
      background: 'var(--buk-blue)'
    }
  }), React.createElement("strong", null, "Comment · ", l.author_name), React.createElement("p", null, l.body, React.createElement("br", null), React.createElement("small", null, new Date(l.created_at).toLocaleString()))))))), React.createElement("div", {
    className: "panel",
    style: {
      marginTop: 18
    }
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Add internal note")), React.createElement("div", {
    className: "panel-body"
  }, React.createElement("div", {
    className: "field"
  }, React.createElement("textarea", {
    value: comment,
    onChange: e => setComment(e.target.value),
    placeholder: "Add a clear note for authorized colleagues..."
  })), React.createElement("button", {
    className: "primary-button",
    style: {
      marginTop: 10
    },
    onClick: addComment
  }, React.createElement("i", {
    className: "fas fa-comment"
  }), "Add note")))), React.createElement("div", null, React.createElement("div", {
    className: "panel"
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Case summary")), React.createElement("div", {
    className: "panel-body"
  }, React.createElement("div", {
    className: "row-meta"
  }, "Student"), React.createElement("div", {
    className: "row-title"
  }, c.student_name), React.createElement("div", {
    className: "row-meta",
    style: {
      marginTop: 14
    }
  }, "Matriculation"), React.createElement("div", {
    className: "row-title"
  }, c.student_matric), React.createElement("div", {
    className: "row-meta",
    style: {
      marginTop: 14
    }
  }, "Incident"), React.createElement("p", {
    style: {
      fontSize: 12,
      lineHeight: 1.6
    }
  }, c.description), React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      flexWrap: 'wrap',
      marginTop: 14
    }
  }, React.createElement("button", {
    className: "primary-button",
    onClick: () => window.print()
  }, React.createElement("i", {
    className: "fas fa-print"
  }), "Print summary"), React.createElement("button", {
    className: "gold-button",
    onClick: () => setShowAppeal(true)
  }, React.createElement("i", {
    className: "fas fa-scale-balanced"
  }), "Submit appeal")))), React.createElement("div", {
    className: "panel",
    style: {
      marginTop: 18
    }
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Hearings")), React.createElement("div", {
    className: "panel-body"
  }, data.hearings?.length ? data.hearings.map(h => React.createElement("div", {
    className: "list-row",
    key: h.id
  }, React.createElement("div", null, React.createElement("div", {
    className: "row-title"
  }, new Date(h.scheduled_date).toLocaleString()), React.createElement("div", {
    className: "row-meta"
  }, h.venue)), React.createElement("span", {
    className: `status-pill ${h.status}`
  }, h.status))) : React.createElement("div", {
    className: "empty-state"
  }, "No hearing scheduled."))), React.createElement("div", {
    className: "panel",
    style: {
      marginTop: 18
    }
  }, React.createElement("div", {
    className: "panel-head"
  }, React.createElement("div", {
    className: "panel-title"
  }, "Appeals")), React.createElement("div", {
    className: "panel-body"
  }, data.appeals?.length ? data.appeals.map(a => React.createElement("div", {
    className: "list-row",
    key: a.id
  }, React.createElement("div", null, React.createElement("div", {
    className: "row-title"
  }, a.status), React.createElement("div", {
    className: "row-meta"
  }, a.grounds)))) : React.createElement("div", {
    className: "empty-state"
  }, "No appeals on this case."))))), showAppeal && React.createElement("div", {
    className: "modal-backdrop"
  }, React.createElement("div", {
    className: "modern-modal"
  }, React.createElement("div", {
    className: "modal-head"
  }, React.createElement("h3", null, "Submit case appeal"), React.createElement("button", {
    className: "icon-button",
    onClick: () => setShowAppeal(false)
  }, React.createElement("i", {
    className: "fas fa-times"
  }))), React.createElement("div", {
    className: "modal-body"
  }, React.createElement("div", {
    className: "field"
  }, React.createElement("label", null, "Grounds for appeal"), React.createElement("textarea", {
    value: grounds,
    onChange: e => setGrounds(e.target.value),
    placeholder: "State the grounds and supporting explanation."
  })), React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'flex-end',
      gap: 9,
      marginTop: 18
    }
  }, React.createElement("button", {
    className: "ghost-button",
    onClick: () => setShowAppeal(false)
  }, "Cancel"), React.createElement("button", {
    className: "primary-button",
    onClick: appeal
  }, "Submit appeal"))))));
}
function Hearings({
  data,
  lookups,
  api,
  notify
}) {
  const [form, setForm] = useState({});
  const submit = async e => {
    e.preventDefault();
    await api('schedule_hearing', form);
    notify('Hearing scheduled');
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
  }, React.createElement("div", null, React.createElement("div", {
    className: "row-title"
  }, h.case_no), React.createElement("div", {
    className: "row-meta"
  }, h.student_name, " · ", h.venue, React.createElement("br", null), new Date(h.scheduled_date).toLocaleString())), React.createElement("span", {
    className: `status-pill ${h.status}`
  }, h.status))) : React.createElement("div", {
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
    onChange: e => setForm({
      ...form,
      case_id: e.target.value
    }),
    placeholder: "Enter case ID"
  })), React.createElement(Field, {
    label: "Date and time"
  }, React.createElement("input", {
    required: true,
    type: "datetime-local",
    value: form.date || '',
    onChange: e => setForm({
      ...form,
      date: e.target.value
    })
  })), React.createElement(Field, {
    label: "Venue",
    style: {
      gridColumn: '1/-1'
    }
  }, React.createElement("select", {
    required: true,
    value: form.venue || '',
    onChange: e => setForm({
      ...form,
      venue: e.target.value
    })
  }, React.createElement("option", {
    value: ""
  }, "Choose venue"), lookups.venues.map(v => React.createElement("option", {
    key: v.id
  }, v.name))))), React.createElement("button", {
    className: "primary-button",
    style: {
      marginTop: 17
    }
  }, React.createElement("i", {
    className: "fas fa-calendar-plus"
  }), "Schedule hearing")))));
}
function Calendar({
  api
}) {
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
  }, React.createElement("div", null, React.createElement("div", {
    className: "row-title"
  }, e.title), React.createElement("div", {
    className: "row-meta"
  }, e.location || 'Institutional calendar', " · ", new Date(e.starts_at).toLocaleString())), React.createElement("span", {
    className: `status-pill ${e.status}`
  }, e.event_type))) : React.createElement("div", {
    className: "empty-state"
  }, "No events scheduled."))));
}
function Reports({
  api
}) {
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
  }, React.createElement("div", {
    className: "row-title"
  }, k), React.createElement("strong", null, v))), !data && React.createElement("div", {
    className: "empty-state"
  }, "Loading analytics..."))), React.createElement("div", {
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
  }, React.createElement("div", {
    className: "row-title"
  }, k), React.createElement("div", {
    style: {
      width: 140,
      height: 8,
      background: '#eaf1f6',
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
function Drafts({api, notify, go}) {
  const [rows, setRows] = useState([]), [title, setTitle] = useState(''), [saving, setSaving] = useState(false);
  const load = () => api('get_drafts').then(r => setRows(r.data || []));
  useEffect(() => { load(); }, []);
  const create = async () => { setSaving(true); await api('save_draft', {title: title || 'Untitled incident draft', payload: {step: 1}, progress: 8}); setTitle(''); await load(); setSaving(false); notify('Draft saved'); };
  const remove = async id => { await api('delete_draft', {draft_id: id}); await load(); notify('Draft deleted'); };
  return React.createElement(React.Fragment, null,
    React.createElement(SectionHead, {eyebrow:'Productivity', title:'My drafts', sub:'Continue incomplete incident reports without losing your work.', action:React.createElement('button',{className:'primary-button',onClick:create,disabled:saving},React.createElement('i',{className:'fas fa-plus'}),saving?'Saving...':'New draft')}),
    React.createElement('div',{className:'panel',style:{marginBottom:18}},React.createElement('div',{className:'panel-body'},React.createElement('div',{className:'form-grid'},React.createElement(Field,{label:'Draft title',style:{gridColumn:'1/-1'}},React.createElement('input',{value:title,onChange:e=>setTitle(e.target.value),placeholder:'e.g. CSC 301 incident report — Hall A'})),React.createElement('button',{className:'primary-button',style:{marginTop:4},onClick:create},React.createElement('i',{className:'fas fa-save'}),'Save draft')))),
    React.createElement('div',{className:'panel'},React.createElement('div',{className:'panel-body'},rows.length?rows.map(d=>React.createElement('div',{className:'list-row',key:d.id},React.createElement('div',null,React.createElement('div',{className:'row-title'},d.title),React.createElement('div',{className:'row-meta'},'Updated ',new Date(d.updated_at).toLocaleString())),React.createElement('div',{style:{display:'flex',alignItems:'center',gap:8}},React.createElement('span',{className:'status-pill in_progress'},d.progress,'% complete'),React.createElement('button',{className:'primary-button',onClick:()=>{notify('Draft loaded in incident workspace');go('incidents')}},'Continue'),React.createElement('button',{className:'ghost-button',onClick:()=>remove(d.id)},React.createElement('i',{className:'fas fa-trash'}))))):React.createElement('div',{className:'empty-state'},React.createElement('i',{className:'fas fa-file-pen'}),React.createElement('div',null,'No saved drafts yet.'))))
  );
}
function Announcements({
  api,
  notify
}) {
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
    notify('Announcement published');
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
    style: {
      gridColumn: '1/-1'
    }
  }, React.createElement("input", {
    required: true,
    value: title,
    onChange: e => setTitle(e.target.value)
  })), React.createElement(Field, {
    label: "Message",
    style: {
      gridColumn: '1/-1'
    }
  }, React.createElement("textarea", {
    required: true,
    value: body,
    onChange: e => setBody(e.target.value)
  }))), React.createElement("button", {
    className: "primary-button",
    style: {
      marginTop: 16
    }
  }, React.createElement("i", {
    className: "fas fa-bullhorn"
  }), "Publish announcement"))));
}
function Settings({
  api,
  user,
  notify
}) {
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
    notify('Preferences saved');
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
    onChange: e => setPref({
      ...pref,
      notify_in_app: e.target.checked
    })
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
    onChange: e => setPref({
      ...pref,
      notify_email: e.target.checked
    })
  }), " Email notifications"), React.createElement("button", {
    className: "primary-button",
    onClick: save
  }, React.createElement("i", {
    className: "fas fa-check"
  }), "Save preferences")))));
}
function Field({
  label,
  children,
  style
}) {
  return React.createElement("div", {
    className: "field",
    style: style
  }, React.createElement("label", null, label), children);
}
function Modal() {
  return null;
}
ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(App, null));
