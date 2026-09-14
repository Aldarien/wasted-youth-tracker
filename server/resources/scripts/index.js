function toggleCollapsed(tr) {
  if (tr.classList.contains("expanded")) {
    tr.classList.remove("expanded");
  } else {
    tr.classList.add("expanded");
  }
};
function setup() {
  setupTabs();
  const tableActivity = document.querySelector("#idTableActivity");
  if (!tableActivity) {
    return; // activity table is not present on every page
  }
  const header = tableActivity.rows[0];
  for (var i = 0; i < header.cells.length; i++) {
    const f = function(ii) { return function() { sortActivityTable(ii); }; };
    header.cells[i].addEventListener("click", f(i));
  }
  const trs = document.querySelectorAll("table.collapsible tr");
  trs.forEach(function(tr, index) {
    tr.addEventListener("click", function() { toggleCollapsed(tr); });
  });
  sortActivityTable(0);
}
function setupTabs() {
  if (typeof document.querySelectorAll !== "function") {
    return;
  }
  const tabs = document.querySelectorAll(".section-tab");
  const panels = document.querySelectorAll(".workspace-panel");
  if (!tabs.length || !panels.length) {
    return;
  }
  const selectTab = function(tab) {
    const selectedId = tab.dataset.tab;
    tabs.forEach(function(item) {
      const selected = item === tab;
      item.setAttribute("aria-selected", selected ? "true" : "false");
      item.tabIndex = selected ? 0 : -1;
    });
    panels.forEach(function(panel) {
      const selected = panel.id === selectedId;
      panel.hidden = !selected;
      panel.open = selected;
    });
  };
  tabs.forEach(function(tab, index) {
    tab.addEventListener("click", function() { selectTab(tab); });
    tab.addEventListener("keydown", function(event) {
      if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") {
        return;
      }
      event.preventDefault();
      const nextIndex = (index + (event.key === "ArrowRight" ? 1 : -1) + tabs.length) % tabs.length;
      tabs[nextIndex].focus();
      selectTab(tabs[nextIndex]);
    });
  });
  const initiallySelected = document.querySelector('.section-tab[aria-selected="true"]') || tabs[0];
  selectTab(initiallySelected);
}
function setToday(id) {
  const today = new Date();
  const dateTo =
      today.getFullYear() + '-'
      + String(today.getMonth() + 1).padStart(2, '0') + '-'
      + String(today.getDate()).padStart(2, '0');
  document.querySelector("#" + id).value = dateTo;
}
function setWeekStart() {
  var d = new Date(document.querySelector("#idDateTo").value);
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); // 0 = Sun
  const date =
      d.getFullYear() + '-'
      + String(d.getMonth() + 1).padStart(2, '0') + '-'
      + String(d.getDate()).padStart(2, '0');
  document.querySelector("#idDateFrom").value = date;
}
function setSameDay() {
  document.querySelector("#idDateFrom").value = document.querySelector("#idDateTo").value;
}
function submitWithUiState(elem, confirmMessage = null) {
  if (confirmMessage !== null && !confirm(confirmMessage)) {
    return false;
  }
  // Do we need to check for value != ""?
  const selectedTab = document.querySelector('input.tabRadio:checked');
  if (selectedTab) {
    const inputSelectedTab = document.createElement('input');
    inputSelectedTab.type = 'hidden';
    inputSelectedTab.name = 'selectedTab';
    inputSelectedTab.value = selectedTab.id;
    elem.form.appendChild(inputSelectedTab);
  }
  const selectedUser = document.querySelector('#idUsers');
  if (selectedUser) {
    const inputSelectedUser = document.createElement('input');
    inputSelectedUser.type = 'hidden';
    inputSelectedUser.name = 'selectedUser';
    inputSelectedUser.value = selectedUser.value;
    elem.form.appendChild(inputSelectedUser);
  }
  for (const [id, name] of [['idDateFrom', 'dateFrom'], ['idDateTo', 'dateTo']]) {
    const dateInput = document.querySelector(`#${id}`);
    if (!dateInput) {
      continue;
    }
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = dateInput.value;
    elem.form.appendChild(input);
  }
  if (elem.type !== "submit") {
    elem.form.submit();
  }
  // Undefined return value will proceed with submit action.
}
function sortActivityTable(column) {
  const table = document.getElementById("idTableActivity");
  const header = table.rows[0];
  var updated = true;
  const sortByTimeOrDate = column <= 1;
  // Default (first click) sort order is descending for date and time columns, and ascending for
  // class and title.
  var descending = sortByTimeOrDate;
  if ("descending" in header.cells[column]) {
    descending = !header.cells[column].descending;
  }
  // Clear sort markers for other columns.
  for (var i = 0; i < header.cells.length; i++) {
    if (i !== column) {
      delete header.cells[i].descending;
    }
  }
  while (updated) {
    updated = false;
    const rows = table.rows;
    // Skip header row 0.
    for (var i = 1; i < rows.length - 1; i++) {
      // Lexicographic comparison works for all columns.
      var a = rows[i].getElementsByTagName("TD")[column].innerHTML.toLowerCase();
      var b = rows[i + 1].getElementsByTagName("TD")[column].innerHTML.toLowerCase();
      // When sorting by date, always sort 2nd descending by time.
      var swap;
      if (sortByTimeOrDate && a === b) {
        a = rows[i].getElementsByTagName("TD")[1 - column].innerHTML;
        b = rows[i + 1].getElementsByTagName("TD")[1 - column].innerHTML;
        swap = a < b;
      } else {
        swap = descending ? a < b : a > b;
      }
      if (swap) {
        rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
        updated = true;
      }
    }
  }
  header.cells[column].descending = descending;
}
