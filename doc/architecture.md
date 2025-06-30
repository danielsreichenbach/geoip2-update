# GeoIP2 Update Architecture

This document describes the architecture of the geoip2-update Composer plugin (version 3.x).

## Overview

The plugin follows a modular, event-driven architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────────┐
│                         Composer Events                         │
│            (POST_INSTALL_CMD, POST_UPDATE_CMD)                 │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    GeoIP2UpdatePlugin                           │
│  - Implements PluginInterface                                   │
│  - Implements EventSubscriberInterface                          │
│  - Implements Capable (CommandProvider)                        │
└────────────────┬───────────────────────────┬────────────────────┘
                 │                           │
                 ▼                           ▼
┌────────────────────────────┐  ┌─────────────────────────────────┐
│      UpdateCommand         │  │        CommandProvider          │
│  - Manual database updates │  │  - Provides CLI commands        │
│  - Force update option     │  │  - Integration with Composer    │
│  - Edition selection       │  │                                 │
└────────────────┬───────────┘  └─────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DatabaseUpdater                            │
│  - Orchestrates the update process                              │
│  - Manages update lifecycle                                     │
│  - Dispatches events                                            │
└────────┬──────────────┬──────────────┬─────────────────────────┘
         │              │              │
         ▼              ▼              ▼
┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│ Downloader  │ │  Extractor  │ │ FileManager │
│             │ │             │ │             │
│ - Downloads │ │ - Extracts  │ │ - Manages   │
│   databases │ │   archives  │ │   files     │
│ - Checks    │ │ - Validates │ │ - Validates │
│   versions  │ │   content   │ │   paths     │
└─────────────┘ └─────────────┘ └─────────────┘
```

## Component Details

### Plugin Layer

#### GeoIP2UpdatePlugin
The main entry point that integrates with Composer:
- Implements `PluginInterface` for Composer integration
- Implements `EventSubscriberInterface` to respond to Composer events
- Implements `Capable` to provide custom commands
- Only runs updates in dev mode by default

#### CommandProvider
Provides the `geoip2:update` command to Composer:
- Allows manual database updates
- Supports force updates and specific edition selection

### Core Components

#### DatabaseUpdater
The orchestrator that manages the entire update workflow:
- Coordinates downloads, extraction, and file management
- Fires events during the update lifecycle
- Handles error collection and reporting
- Supports force updates to bypass version checks

#### Downloader
Handles communication with MaxMind's API:
- Authenticates using account ID and license key
- Downloads database archives
- Checks remote version information
- Provides progress tracking capability

#### Extractor
Manages archive extraction:
- Extracts tar.gz archives using PharData
- Validates extracted content
- Finds the database file within the archive
- Handles cleanup of temporary files

#### FileManager
Provides file system operations:
- Creates and validates directories
- Manages file permissions
- Handles atomic file operations
- Cleans up temporary files

### Configuration

#### ConfigBuilder
Builds configuration from various sources:
- Reads from `composer.json` extra section
- Validates all configuration parameters
- Provides sensible defaults
- Supports the following options:
  - `maxmind-account-id`: Required MaxMind account ID
  - `maxmind-license-key`: Required MaxMind license key
  - `maxmind-database-editions`: Array of editions to download
  - `maxmind-database-folder`: Directory to store databases

#### Config Model
Immutable configuration object:
- Validates configuration on construction
- Provides type-safe access to settings
- Supports individual edition overrides

### Event System

The plugin dispatches events throughout the update lifecycle:

#### PreUpdateEvent
Fired before any updates begin:
- Contains the configuration being used
- Can be used to modify configuration before updates

#### DatabaseUpdateEvent
Fired before each database update:
- Contains the edition being updated
- Listeners can skip specific databases
- Provides access to configuration

#### PostUpdateEvent
Fired after all updates complete:
- Contains update results
- Provides success/failure counts
- Can be used for notifications

#### UpdateErrorEvent
Fired when an error occurs:
- Contains the exception details
- Identifies which edition failed
- Useful for error tracking

### Factory Pattern

The `Factory` class provides centralized component creation:
- Implements singleton pattern for shared components
- Allows easy testing through `reset()` method
- Creates all core components with proper dependencies
- Reduces coupling between components

## Design Principles

### Separation of Concerns
Each component has a single, well-defined responsibility:
- Plugin layer handles Composer integration
- Core components handle specific tasks
- Configuration is separate from logic
- Events provide extensibility

### Dependency Injection
Components receive dependencies through constructors:
- Improves testability
- Makes dependencies explicit
- Allows easy mocking in tests

### Event-Driven Architecture
Events allow extension without modification:
- Custom listeners can modify behavior
- Provides hooks for logging/monitoring
- Maintains loose coupling

### Immutable Configuration
Configuration objects are immutable:
- Prevents accidental modification
- Thread-safe by design
- Easier to reason about

### Error Handling
Errors are collected and reported:
- Individual edition failures don't stop others
- Detailed error messages for debugging
- Clear success/failure reporting

## Testing Strategy

### Unit Tests
Each component is thoroughly unit tested:
- Mocked dependencies
- Edge case coverage
- Error condition testing

### Integration Tests
Key workflows are integration tested:
- Complete update workflow
- Composer plugin integration
- Command-line interface

### Test Utilities
Supporting test infrastructure:
- vfsStream for file system mocking
- Factory reset for test isolation
- Fixture data for consistent testing

## Security Considerations

### Authentication
- Uses HTTPS for all API calls
- HTTP Basic Auth with account ID and license key
- Credentials never logged or exposed

### File Operations
- Validates all file paths
- Uses atomic operations where possible
- Cleans up temporary files

### Input Validation
- All configuration is validated
- Command input is sanitized
- File paths are checked for directory traversal

## Performance Considerations

### Efficient Downloads
- Only downloads when updates are available
- Version checking prevents unnecessary downloads
- Progress tracking for large files

### Memory Usage
- Streams large files instead of loading into memory
- Cleans up temporary files promptly
- Extracts directly to final location

### Singleton Components
- Shared components reduce memory usage
- Factory pattern prevents duplicate instances
- Lazy initialization where appropriate

## Future Enhancements

### Planned Features
- Parallel downloads for multiple editions
- Incremental update support
- Automatic retry with exponential backoff
- Database integrity verification

### Extension Points
- Custom download strategies
- Alternative storage backends
- Metrics and monitoring hooks
- Custom event listeners

## Migration from v2

Version 3 is a complete rewrite with no shared code:
- Modern PHP 8.0+ features
- Proper Composer plugin architecture
- Comprehensive test coverage
- Event-driven extensibility

See [UPGRADING.md](../UPGRADING.md) for migration instructions.