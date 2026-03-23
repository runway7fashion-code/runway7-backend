---
name: feedback_status_active_rule
description: Regla crítica sobre cuándo y quién puede cambiar el status de un usuario a active
type: feedback
---

No asumir que lógica existente en el código es "intencional" sin verificar con el usuario primero. En particular, se asumió incorrectamente que cambiar el status de voluntarios a `active` al enviar el email/SMS de onboarding era intencional. Era un error.

**Why:** El usuario lo corrigió — el onboarding email/SMS es solo un mensaje de bienvenida, no debe cambiar el status.

**How to apply:**

## Regla de status `active` para usuarios

El status de un usuario **solo cambia a `active` cuando**:
- Hace **login por primera vez** en la app móvil o en la página web (cuando se implemente)
- Esto aplica para **todos los roles**: volunteer, model, designer, media, etc.

## Quién puede cambiar status a `active`

- **Solo `admin`** puede cambiar manualmente el status de un usuario a `active`
- **`operation`, `pr`, ni ningún otro rol** puede cambiar el status a `active`
- El email/SMS de onboarding NO cambia el status — es solo un mensaje informativo

## Acciones que NO deben cambiar status a `active`

- Envío de email de onboarding (individual o bulk)
- Envío de SMS de onboarding (individual o bulk)
- Cualquier acción administrativa que no sea el admin explícitamente cambiando el campo
