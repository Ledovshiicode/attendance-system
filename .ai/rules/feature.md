---
paths:
  - 'tests/Feature/**/*PanelTest.php'
---

# Feature

## Set Filament panel for employee Livewire tests
Livewire tests for employee-panel resources/pages must authenticate the employee user, then call Filament::setCurrentPanel('employee') and Filament::bootCurrentPanel() before mounting components. Otherwise route generation falls back to the default admin panel.
