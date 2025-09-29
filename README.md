# Solwed WP - Plugin Profesional de WordPress

# Solwed WP - Documentación Unificada

<div align="center">

Plugin profesional para WordPress que integra FacturaScripts API y configuración SMTP avanzada, con seguridad reforzada y personalización visual.

![Solwed WP Plugin](assets/img/LogotipoClaro.png)

---

**Plugin profesional para WordPress desarrollado por Solwed - Soluciones Web a Medida**

## 🚀 Instalación y Estado

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759B.svg)](https://wordpress.org/)

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://php.net/)1. Sube la carpeta `solwed-wp` al directorio `/wp-content/plugins/`

[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)2. Activa el plugin desde el panel de administración de WordPress

[![Version](https://img.shields.io/badge/Version-2.1.0-blue.svg)](CHANGELOG.md)3. Ve a **Solwed WP** en el menú de administración

4. Configura FacturaScripts y/o SMTP según tus necesidades

</div>

**Estado:** Completamente funcional y listo para producción

## 🚀 Acerca de Solwed

---

**Solwed - Solutions Website Design** es una empresa especializada en desarrollo web profesional ubicada en Mérida, Badajoz. Ofrecemos soluciones digitales integrales para empresas que buscan destacar en el mundo digital.

## 📁 Estructura del Plugin

### Nuestros Servicios:

- **Desarrollo Web Profesional** - Sitios web modernos y funcionales```

- **Gestión de Hosting y Cloud** - Almacenamiento seguro en la nubesolwed-wp/

- **Sistema de Facturación Electrónica** - Gestión empresarial completa├── solwed-wp.php                    # Archivo principal

- **Consultoría Digital** - Estrategias para hacer crecer tu negocio├── README.md                        # Documentación

- **Kit Digital y Subvenciones** - Ayudamos a solicitar ayudas públicas├── examples.php                     # Ejemplos de uso

- **Soporte Técnico 12/5** - Atención especializada├── includes/

│   ├── install.php                  # Funciones de instalación

🌐 **Web:** [solwed.es](https://solwed.es)  │   ├── admin-page.php               # Dashboard

📧 **Email:** hola@solwed.es  │   ├── settings-page.php            # Configuración

📞 **Teléfono:** 644 83 19 16  │   ├── api/

📍 **Dirección:** Av. de Lusitania, 51, 06800 Mérida, Badajoz│   │   ├── facturascripts-api.php   # API FacturaScripts

│   │   └── smtp-handler.php         # Manejador SMTP

---│   └── settings-tabs/

│       ├── facturascripts-tab.php   # Pestaña FacturaScripts

## 📋 Descripción del Plugin│       └── smtp-tab.php             # Pestaña SMTP

├── assets/

**Solwed WP** es un plugin profesional todo-en-uno que integra las mejores prácticas de desarrollo web en WordPress. Diseñado para agencias, desarrolladores y empresas que buscan una solución completa de seguridad, comunicación y branding corporativo.│   ├── css/

│   │   └── admin.css                # Estilos

### 🎯 Características Principales│   └── js/

│       └── admin.js                 # JavaScript

#### 🔐 **Módulo de Seguridad Avanzada**```

- **Protección contra ataques de fuerza bruta** con limitación configurable de intentos

- **Registro de actividad de seguridad** con logs detallados---

- **Bloqueo automático por IP** con duración personalizable

- **Monitoreo de usuarios sospechosos** en tiempo real## 🛠️ Características Principales

- **Dashboard de seguridad** con estadísticas en vivo

- Integración con FacturaScripts API

#### 📧 **Sistema SMTP Profesional**- Configuración SMTP avanzada

- **Configuración SMTP automática** lista para usar- Seguridad en el login (URL personalizada, bloqueo de acceso directo)

- **Puerto 465 SSL por defecto** para máxima seguridad- Personalización de fondo y logo en la página de login

- **Gestión segura de contraseñas** con ocultación automática- Barra de branding en el footer de todas las páginas

- **Nombre de remitente personalizado** (Solwed.es✌️)- Dashboard centralizado con logs y actividad reciente

- **Test de conexión integrado** para verificar configuración- Sistema de logs y base de datos

- **Registro de emails enviados** para auditoría- Hooks y extensibilidad para desarrolladores

- **Compatibilidad total** con Gmail, Outlook, SMTP personalizados

---

#### 🎨 **Sistema de Personalización Glassmorphism**

- **Diseño glassmorphism moderno** con efectos de cristal## ⚙️ Configuración

- **Login personalizado** con branding corporativo

- **Colores corporativos integrados** (Amarillo #F2E501, Grises corporativos)### FacturaScripts

- **Tipografía Orbitron** profesional- URL completa de tu instalación

- **Efectos visuales avanzados** con blur y transparencias- API Key generada en FacturaScripts

- **Responsive design** adaptado a todos los dispositivos- Company ID (opcional)

- Sincronización automática configurable

#### 🏷️ **Branding Corporativo Avanzado**

- **Footer personalizado** en wp-admin: "Gracias por crear con Solwed.es✌️"### SMTP

- **Enlaces corporativos** con colores amarillos característicos- Servidor SMTP personalizado

- **Banner inferior configurable** con información corporativa- Presets para Gmail, Outlook, Yahoo

- **Logo personalizado** en login y admin- Encriptación TLS/SSL

- **Colores de enlaces** corporativos en toda la administración- Guardado de emails en base de datos

- Emails de prueba y estadísticas

### 🔧 **Funcionalidades Técnicas**

#### Ejemplo de integración SMTP

#### 💾 **Gestión de Base de Datos**```php

- **Creación automática de tablas** especializadasupdate_option('solwed_wp_smtp_host', 'smtp.tuservidor.com');

- **Sistema de logs robusto** para seguridad y SMTPupdate_option('solwed_wp_smtp_user', 'usuario@tuservidor.com');

- **Optimización de consultas** para mejor rendimientoupdate_option('solwed_wp_smtp_pass', 'tu_clave');

- **Limpieza automática** de datos obsoletos```



#### ⚙️ **Configuración Inteligente**---

- **Instalación automática** con configuración por defecto

- **Panel de administración unificado** con pestañas organizadas## � Seguridad

- **Configuración SMTP automática** al activar el plugin

- **Valores predeterminados optimizados** para uso inmediato- Acceso al login solo por URL personalizada

- Limitación de intentos y bloqueo temporal

#### 🛡️ **Seguridad y Compatibilidad**- Logging detallado de actividad y errores

- **Compatible con PHP 8.1, 8.2, 8.3 y 8.4**- Protección contra ataques: IP, User Agent, patrones

- **Tested con WordPress 5.0+ hasta 6.4+**- Nonces para protección CSRF

- **Código sanitizado** siguiendo estándares WordPress- Validación y sanitización de datos

- **Escape de datos** en todas las salidas- Encriptación de contraseñas sensibles

- **Validación de nonces** en formularios

- **Filtros de seguridad** en todas las entradas---



---## 🎨 Personalización Visual



## 📦 **Instalación**- Fondo y logo del login personalizables

- Barra de branding en el footer

### Método 1: Instalación Manual- Dashboard moderno y responsive

1. Descarga el plugin desde el repositorio oficial- Cards informativos, modales, animaciones CSS

2. Sube la carpeta `solwed-wp` a `/wp-content/plugins/`

3. Activa el plugin desde el panel de administración de WordPress---

4. ¡Listo! El plugin se configurará automáticamente

## 📊 Dashboard y Logs

### Método 2: Instalación desde Admin

1. Ve a **Plugins → Añadir nuevo** en tu WordPress- Estado de conexiones en tiempo real

2. Busca "Solwed WP"- Estadísticas de sincronizaciones y emails

3. Instala y activa el plugin- Actividad reciente y logs detallados

4. La configuración inicial se aplicará automáticamente- Información del sistema y pruebas instantáneas



---### Tipos de Log

- facturascripts: Operaciones de API

## ⚡ **Configuración Rápida**- smtp: Envío de emails

- settings: Cambios de configuración

### 🎯 **Configuración Automática al Activar**

---

El plugin está diseñado para funcionar inmediatamente después de la activación:

##  Sincronización Automática

- ✅ **SMTP activado** con configuración óptima

- ✅ **Seguridad activada** con límites recomendados- Intervalos: cada hora, dos veces al día, diario, semanal

- ✅ **Branding corporativo** aplicado automáticamente- Proceso: conexión, descarga, procesamiento, registro y notificación

- ✅ **Base de datos** configurada y optimizada

---

### 🔧 **Personalización Avanzada**

## 📧 Sistema de Emails

Accede a **Solwed WP** en el menú de administración para:

- Reemplazo de wp_mail() por SMTP personalizado

1. **Dashboard** - Estadísticas y estado general- Guardado de historial y estadísticas

2. **Configuración** - Ajustes avanzados por módulos- Plantillas HTML profesionales

3. **Seguridad** - Gestión de intentos y logs- Reintentos automáticos

4. **SMTP** - Configuración de correo electrónico

5. **Apariencia** - Personalización visual y branding---



---## 🧩 Extensibilidad y Hooks



## 📊 **Funcionalidades Detalladas**- login_enqueue_scripts: Personaliza login

- wp_footer: Barra de branding

### 🔐 **Módulo de Seguridad**- admin_menu: Menú y submenús

- wp_ajax_*: AJAX para pruebas y sincronización

```php- solwed_wp_sync_facturascripts: Sincronización automática

// Configuración por defecto- Filtros: wp_mail_from, wp_mail_from_name

- Máximo intentos de login: 3- Acciones: phpmailer_init, wp_mail

- Duración del bloqueo: 30 minutos

- Registro de actividad: ActivadoEjemplo de hook personalizado:

- Monitoreo IP: Automático```php

```add_action('solwed_wp_custom_hook', function() {

	// Tu código personalizado

**Características técnicas:**});

- Detección de patrones de ataque automatizada```

- Sistema de whitelist para IPs de confianza

- Logs estructurados con timestamp y detalles---

- Dashboard con gráficos en tiempo real

- Notificaciones de seguridad por email## 📝 Ejemplos de Uso



### 📧 **Sistema SMTP**Consulta el archivo `examples.php` para ver ejemplos completos de integración con FacturaScripts, SMTP, hooks, filtros y shortcodes.



```php---

// Configuración automática

- Puerto: 465 (SSL)## 📄 Licencia y Soporte

- Encriptación: SSL/TLS

- Nombre remitente: "Solwed.es✌️"Licencia: GPL v2 o posterior

- Contraseña: Protegida y ocultada

```Soporte y mejoras: [Solwed](https://solwed.es) | Email: soporte@solwed.com



**Funcionalidades avanzadas:**---

- Test de conexión con diagnóstico completo

- Registro de emails para auditoría**Desarrollado por Solwed** - Soluciones web profesionales
- Compatible con servicios populares (Gmail, Outlook)
- Configuración de backup automática
- Estadísticas de entrega y errores

### 🎨 **Sistema Glassmorphism**

**Efectos visuales aplicados:**
- `backdrop-filter: blur(20px)` - Efecto cristal
- Transparencias con `rgba()` optimizadas
- Colores corporativos integrados
- Tipografía Orbitron desde Google Fonts
- Animaciones CSS3 suaves
- Responsive design mobile-first

**Variables CSS corporativas:**
```css
--solwed-primary: #2E3536;    /* Gris corporativo */
--solwed-accent: #F2E501;     /* Amarillo distintivo */
--solwed-white: #ffffff;      /* Blanco puro */
```

---

## 🛠️ **Requisitos del Sistema**

### Requisitos Mínimos
- **WordPress:** 5.0 o superior
- **PHP:** 8.1 o superior
- **MySQL:** 5.7 o superior
- **Memoria PHP:** 64MB mínimo (128MB recomendado)
- **Espacio en disco:** 5MB

### Requisitos Recomendados
- **WordPress:** 6.0+ (última versión estable)
- **PHP:** 8.3 o 8.4 (máximo rendimiento)
- **MySQL:** 8.0+ o MariaDB 10.5+
- **Memoria PHP:** 256MB o superior
- **HTTPS:** SSL/TLS habilitado (recomendado para SMTP)

### Compatibilidad Verificada
- ✅ **PHP 8.1, 8.2, 8.3, 8.4**
- ✅ **WordPress 5.0 hasta 6.4+**
- ✅ **Multisitio WordPress** (Network)
- ✅ **Principales temas y plugins**
- ✅ **Hosting compartido y VPS**

---

## 🎯 **Casos de Uso**

### 👨‍💼 **Para Agencias Web**
- Branding corporativo automático en todos los proyectos
- Sistema SMTP configurado para comunicaciones cliente
- Seguridad avanzada para sitios empresariales
- Dashboard unificado para gestión múltiple

### 🏢 **Para Empresas**
- Identidad corporativa integrada en WordPress
- Comunicaciones SMTP profesionales
- Monitoreo de seguridad empresarial
- Gestión centralizada de configuraciones

### 👩‍💻 **Para Desarrolladores**
- Base de código limpia y documentada
- Hooks y filtros personalizables
- API extendible para funcionalidades adicionales
- Compatibilidad con frameworks populares

---

## 📞 **Soporte y Contacto**

### 🆘 **Soporte Técnico Profesional**

**Horarios:** Lunes a Viernes, 8:00 - 20:00 (GMT+1)

- 🌐 **Web:** [solwed.es/contacto](https://solwed.es/contacto)
- 📧 **Email:** hola@solwed.es
- 📞 **Teléfono:** 644 83 19 16
- 💬 **Chat en vivo:** Disponible en nuestra web

### 🏢 **Información Corporativa**

**Solutions Website Design SLU**  
**CIF:** B55406862  
**Dirección:** Av. de Lusitania, 51, 06800 Mérida, Badajoz, España

---

## 📝 **Licencia y Derechos de Autor**

```
Solwed WP Plugin
Copyright (C) 2025 Solutions Website Design SLU

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## 🙏 **Agradecimientos**

Desarrollado con ❤️ por el equipo de **Solwed - Solutions Website Design**

**¿Te gusta nuestro trabajo?** 
- ⭐ [Deja una reseña en Google](https://g.page/r/CXSeG1UvmEqFEBM/review)
- 📢 Recomiéndanos a otros profesionales
- 🤝 Síguenos en nuestras redes sociales

---

<div align="center">

**Powered by [Solwed.es](https://solwed.es) ✌️**

*Soluciones Web a Medida - Tu negocio en nuestras manos*

</div>