#!/usr/bin/env bash
set -e

# Disable gh update notifications
export GH_NO_UPDATE_NOTIFIER=1

# Main file where the version number lives.
MAIN_FILE="newspack-migration-tools.php"

# Make text red.
print_red() {
    echo -e "\033[31m$1\033[0m"
}

# Check prerequisites before creating a release.
check_prerequisites() {
    # Is gh installed?
    if ! command -v gh &> /dev/null; then
        print_red "The 'gh' CLI tool is not installed. Please install it from https://cli.github.com in order to use this release script."
        exit 1
    fi

    # Are we on trunk?
    local current_branch
    current_branch=$(git rev-parse --abbrev-ref HEAD)
    if [ "$current_branch" != "trunk" ]; then
        print_red "You must be on the 'trunk' branch to create a release. Current branch: $current_branch"
        exit 1
    fi

    # Do we have uncommitted changes?
    if [ -n "$(git status --porcelain)" ]; then
        print_red "You have uncommitted changes. Please commit or stash them before creating a release."
        exit 1
    fi
}

# Extract the current version from the main file.
get_current_version() {
    local version
    version=$(grep "Version:" "$MAIN_FILE" | sed -E 's/.*Version: +([0-9]+\.[0-9]+\.[0-9]+).*/\1/')

    if [ -z "$version" ]; then
        print_red "Error: Could not find version in $MAIN_FILE"
        exit 1
    fi

    echo "$version"
}

# Update the version number in the main file.
update_version() {
    local new_version=$1

    # Update the version in the main file
    sed -i.bak -E "s/(Version: +)[0-9]+\.[0-9]+\.[0-9]+/\1$new_version/" "$MAIN_FILE" && rm "${MAIN_FILE}.bak"

    printf " * Updated version in %s to %s\n" "$MAIN_FILE" "$new_version"
}

# Main release workflow.
main() {
    check_prerequisites

    # Pull latest changes from remote
    echo "Pulling latest changes from origin/trunk..."
    git fetch origin trunk
    git pull origin trunk
    printf " * Up to date with remote\n"
    echo ""

    # Get current version
    local current_version
    current_version=$(get_current_version)
    echo "Current version is: $current_version"

    # Ask for release type as a numeric choice
    echo ""
    echo "Select release type:"
    echo "  1) patch"
    echo "  2) minor"
    echo "  3) major"
    printf "Enter choice [1-3]: "
    read -r choice

    # Validate numeric choice
    if ! [[ "$choice" =~ ^[1-3]$ ]]; then
        echo "Invalid choice. Enter a number: 1 (patch), 2 (minor), or 3 (major)."
        exit 1
    fi

    # Calculate new version
    local major minor patch
    IFS='.' read -r major minor patch <<< "$current_version"

    if [ "$choice" -eq 1 ]; then
        # patch
        patch=$((patch + 1))
    elif [ "$choice" -eq 2 ]; then
        # minor
        minor=$((minor + 1))
        patch=0
    else
        # major
        major=$((major + 1))
        minor=0
        patch=0
    fi

    local new_version="$major.$minor.$patch"

    # Confirm with user
    printf "\nNew version will be: \033[1m%s\033[0m Do you want to proceed with this version? [y/N]: " "$new_version"
    read -r confirm

    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        echo "Release cancelled."
        exit 0
    fi

    # Create the release
    echo ""
    echo "-------------------------------------"
    echo "Creating release..."
    echo "-------------------------------------"

    update_version "$new_version"

    git add "$MAIN_FILE"
    git commit -m "Bump version to $new_version"
    printf " * Created commit\n"

    git tag "v$new_version"
    printf " * Created tag v%s\n" "$new_version"

    git push && git push --tags
    printf " * Pushed changes and tags\n"

    gh release create "v$new_version" --generate-notes release/newspack-migration-tools.zip
    printf " * Created GitHub release with zip file\n"

    echo ""
    echo "-------------------------------------"
    echo "Release v$new_version created successfully!"
    echo "-------------------------------------"
}

# Run main function
#main
update_version 1.2.2